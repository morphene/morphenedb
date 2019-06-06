from datetime import datetime, timedelta
from morphenepython import MorpheneClient
from pymongo import MongoClient
from pprint import pprint
import collections
import time
import sys
import os
import re

from apscheduler.schedulers.background import BackgroundScheduler

fullnodes = [
    'http://morphene-witness:8090',
    # 'http://localhost:8091',
    # 'https://morphene.io/rpc',
]
mph = MorpheneClient(fullnodes)
mongo = MongoClient("mongodb://mongo:27017")
db = mongo.morphenedb

mvest_per_account = {}

def load_accounts():
    pprint("[MORPHENE] - Loading mvest per account")
    for account in db.account.find():
        if "name" in account.keys():
            mvest_per_account.update({account['name']: account['vesting_shares']})

def update_props_history():
    pprint("[MORPHENE] - Update Global Properties")

    props = mph.rpc.get_dynamic_global_properties()

    for key in ['recent_slots_filled']:
        props[key] = float(props[key])
    for key in ['current_supply', 'total_vesting_fund_morph', 'total_vesting_shares']:
        props[key] = float(props[key].split()[0])
    for key in ['time']:
        props[key] = datetime.strptime(props[key], "%Y-%m-%dT%H:%M:%S")

    #floor($return['total_vesting_fund_morph'] / $return['total_vesting_shares'] * 1000000 * 1000) / 1000;

    props['morph_per_mvests'] = props['total_vesting_fund_morph'] / props['total_vesting_shares'] * 1000000

    db.status.update_one({
      '_id': 'morph_per_mvests'
    }, {
      '$set': {
        '_id': 'morph_per_mvests',
        'value': props['morph_per_mvests']
      }
    }, upsert=True)

    db.status.update_one({
      '_id': 'props'
    }, {
      '$set': {
        '_id': 'props',
        'props': props
      }
    }, upsert=True)

    db.props_history.insert_one(props)

def update_tx_history():
    pprint("[MORPHENE] - Update Transaction History")
    now = datetime.now().date()

    today = datetime.combine(now, datetime.min.time())
    yesterday = today - timedelta(1)

    # Determine tx per day
    query = {
      '_ts': {
        '$gte': today,
        '$lte': today + timedelta(1)
      }
    }
    count = db.block_30d.count(query)

    pprint(count)

    pprint(now)
    pprint(today)
    pprint(yesterday)



def update_history():

    update_props_history()
    # update_tx_history()
    # sys.stdout.flush()

    # Load all accounts
    users = mph.rpc.lookup_accounts(-1, 1000)
    more = True
    while more:
        newUsers = mph.rpc.lookup_accounts(users[-1], 1000)
        if len(newUsers) < 1000:
            more = False
        users = users + newUsers

    # Set dates
    now = datetime.now().date()
    today = datetime.combine(now, datetime.min.time())

    pprint("[MORPHENE] - Update History (" + str(len(users)) + " accounts)")
    # Snapshot User Count
    db.statistics.replace_one({
      'key': 'users',
      'date': today,
    }, {
      'key': 'users',
      'date': today,
      'value': len(users)
    }, upsert=True)
    sys.stdout.flush()

    # Update history on accounts
    for user in users:
        # Load State
        state = mph.rpc.get_accounts([user])
        # Get Account Data
        account = collections.OrderedDict(sorted(state[0].items()))
        # Convert to Numbers
        account['proxy_witness'] = sum(float(i) for i in account['proxied_vsf_votes']) / 1000000
        for key in ['to_withdraw']:
            account[key] = float(account[key])
        for key in ['balance', 'vesting_balance', 'vesting_shares', 'vesting_withdraw_rate']:
            account[key] = float(account[key].split()[0])
        # Convert to Date
        for key in ['created','last_account_recovery','last_account_update','last_owner_update','next_vesting_withdrawal']:
            account[key] = datetime.strptime(account[key], "%Y-%m-%dT%H:%M:%S")
        # Combine Savings + Balance
        account['total_balance'] = account['balance']
        # Update our current info about the account
        mvest_per_account.update({account['name']: account['vesting_shares']})
        # Save current state of account
        account['scanned'] = datetime.now()
        db.account.replace_one({'_id': user}, account, upsert=True)
        # Create our Snapshot dict
        wanted_keys = ['name', 'proxy_witness', 'activity_shares', 'average_bandwidth', 'average_market_bandwidth', 'balance', 'next_vesting_withdrawal', 'to_withdraw', 'vesting_balance', 'vesting_shares', 'vesting_withdraw_rate', 'withdraw_routes', 'withdrawn', 'witnesses_voted_for']
        snapshot = dict((k, account[k]) for k in wanted_keys if k in account)
        snapshot.update({
          'account': user,
          'date': today
        })
        # Save Snapshot in Database
        db.account_history.replace_one({
          'account': user,
          'date': today
        }, snapshot, upsert=True)

def update_stats():
  pprint("updating stats");
  # Calculate Transactions
  results = db.block_30d.aggregate([
    {
      '$sort': {
        '_id': -1
      }
    },
    {
      '$limit': 28800 * 1
    },
    {
      '$unwind': '$transactions'
    },
    {
      '$group': {
        '_id': '24h',
        'tx': {
          '$sum': 1
        }
      }
    }
  ])
  data = list(results)
  if data:
    data = data[0]['tx']
  db.status.update_one({'_id': 'transactions-24h'}, {'$set': {'data' : data}}, upsert=True)
  now = datetime.now().date()
  today = datetime.combine(now, datetime.min.time())
  db.tx_history.update_one({
    'timeframe': '24h',
    'date': today
  }, {'$set': {'data': data}}, upsert=True)

  results = db.block_30d.aggregate([
    {
      '$sort': {
        '_id': -1
      }
    },
    {
      '$limit': 1200 * 1
    },
    {
      '$unwind': '$transactions'
    },
    {
      '$group': {
        '_id': '1h',
        'tx': {
          '$sum': 1
        }
      }
    }
  ])
  data = list(results)
  if data:
    data[0]['tx']
  db.status.update_one({'_id': 'transactions-1h'}, {'$set': {'data' : data}}, upsert=True)

  # Calculate Operations
  results = db.block_30d.aggregate([
    {
      '$sort': {
        '_id': -1
      }
    },
    {
      '$limit': 28800 * 1
    },
    {
      '$unwind': '$transactions'
    },
    {
      '$group': {
        '_id': '24h',
        'tx': {
          '$sum': {
            '$size': '$transactions.operations'
          }
        }
      }
    }
  ])
  data = list(results)
  if data:
    data = data[0]['tx']
  db.status.update_one({'_id': 'operations-24h'}, {'$set': {'data' : data}}, upsert=True)
  now = datetime.now().date()
  today = datetime.combine(now, datetime.min.time())
  db.op_history.update_one({
    'timeframe': '24h',
    'date': today
  }, {'$set': {'data': data}}, upsert=True)

  results = db.block_30d.aggregate([
    {
      '$sort': {
        '_id': -1
      }
    },
    {
      '$limit': 1200 * 1
    },
    {
      '$unwind': '$transactions'
    },
    {
      '$group': {
        '_id': '1h',
        'tx': {
          '$sum': {
            '$size': '$transactions.operations'
          }
        }
      }
    }
  ])
  data = list(results)
  if data:
    data[0]['tx']
  db.status.update_one({'_id': 'operations-1h'}, {'$set': {'data' : data}}, upsert=True)


if __name__ == '__main__':
    pprint("starting");
    # Load all account data into memory

    # Start job immediately
    update_props_history()
    load_accounts()
    update_stats()
    update_history()
    sys.stdout.flush()

    # Schedule it to run every 6 hours
    scheduler = BackgroundScheduler()
    scheduler.add_job(update_history, 'interval', hours=24, id='update_history')
    scheduler.add_job(update_stats, 'interval', minutes=5, id='update_stats')
    scheduler.start()
    # Loop
    try:
        while True:
            time.sleep(2)
    except (KeyboardInterrupt, SystemExit):
        scheduler.shutdown()
