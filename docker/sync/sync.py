from datetime import datetime, timedelta
from morphenepython import MorpheneClient
from pymongo import MongoClient
from pprint import pprint
import collections
import json
import time
import sys
import os

fullnodes = [
    'https://morphene.io/rpc',
]
mph = MorpheneClient(fullnodes)
mongo = MongoClient("mongodb://mongo:27017")
db = mongo.morphenedb

init = db.status.find_one({'_id': 'height'})
if(init):
  last_block = init['value']
else:
  last_block = 1

# ------------
# For development:
#
# If you're looking for a faster way to sync the data and get started,
# uncomment this line with a more recent block, and the chain will start
# to sync from that point onwards. Great for a development environment
# where you want some data but don't want to sync the entire blockchain.
# ------------

# last_block = 12766938

def process_op(opObj, block, blockid):
    print(opObj)
    opType = opObj['type'].replace("_operation","")
    op = opObj['value']
    if opType == "account_witness_vote":
        save_witness_vote(op, block, blockid)
    if opType == "pow":
        save_pow(op, block, blockid)
    if opType == "transfer":
        save_transfer(op, block, blockid)
    if opType == "place_bid":
        save_place_bid(op, block, blockid)
    if opType == "create_auction" or opType == "update_auction":
        save_auction(op, block, blockid)
    if opType == "auction_payout":
        save_auction_payout(op, block, blockid)
    if opType == "transfer_to_vesting":
        save_vesting_deposit(op, block, blockid)
    if opType == "fill_vesting_withdraw":
        save_vesting_withdraw(op, block, blockid)

def process_block(block, blockid):
    save_block(block, blockid)
    ops = mph.rpc.get_ops_in_block(blockid, False)
    for tx in block['transactions']:
      for opObj in tx['operations']:
        process_op(opObj, block, blockid)
    for opObj in ops:
      process_op(opObj['op'], block, blockid)

def save_transfer(op, block, blockid):
    transfer = op.copy()
    _id = str(blockid) + '/' + op['from'] + '/' + op['to']
    transfer.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S"),
        'amount': float(transfer['amount'].split()[0]),
        'type': transfer['amount'].split()[1]
    })
    db.transfer.replace_one({'_id': _id}, transfer, upsert=True)
    queue_update_account(op['from'])
    if op['from'] != op['to']:
        queue_update_account(op['to'])

def save_place_bid(op, block, blockid):
    bid = op.copy()
    _id = str(blockid) + '/' + op['bidder'] + '/' + op['permlink']
    bid.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S")
    })
    db.bid.replace_one({'_id': _id}, bid, upsert=True)
    queue_update_account(op['bidder'])

def save_auction(op, block, blockid):
    auction = op.copy()
    _id = str(blockid) + '/' + op['consigner'] + '/' + op['permlink']
    auction.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S"),
        'fee': float(auction['fee'].split()[0]),
    })
    db.auction.replace_one({'_id': _id}, auction, upsert=True)
    queue_update_account(op['consigner'])

def save_auction_payout(op, block, blockid):
    auction_payout = op.copy()
    _id = str(blockid) + '/' + op['account']
    auction_payout.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S"),
        'payout': float(auction_payout['payout'].split()[0]),
    })
    db.auction_payout.replace_one({'_id': _id}, auction_payout, upsert=True)
    queue_update_account(op['account'])

def save_vesting_deposit(op, block, blockid):
    vesting = op.copy()
    _id = str(blockid) + '/' + op['from'] + '/' + op['to']
    vesting.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S"),
        'amount': float(vesting['amount'].split()[0])
    })
    db.vesting_deposit.replace_one({'_id': _id}, vesting, upsert=True)
    queue_update_account(op['from'])
    if op['from'] != op['to']:
        queue_update_account(op['to'])

def save_vesting_withdraw(op, block, blockid):
    vesting = op.copy()
    _id = str(blockid) + '/' + op['from_account'] + '/' + op['to_account']
    vesting.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S")
    })
    for key in ['deposited', 'withdrawn']:
        vesting[key] = float(vesting[key].split()[0])
    db.vesting_withdraw.update({'_id': _id}, vesting, upsert=True)
    queue_update_account(op['from_account'])
    if op['from_account'] != op['to_account']:
        queue_update_account(op['to_account'])

def save_block(block, blockid):
    doc = block.copy()
    doc.update({
        '_id': blockid,
        '_ts': datetime.strptime(doc['timestamp'], "%Y-%m-%dT%H:%M:%S"),
    })
    db.block_30d.replace_one({'_id': blockid}, doc, upsert=True)

def save_pow(op, block, blockid):
    _id = str(blockid) + '-' + op["work"]["value"]["input"]["worker_account"]
    doc = op.copy()
    doc.update({
        '_id': _id,
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S"),
        'block': blockid,
    })
    db.pow.replace_one({'_id': _id}, doc, upsert=True)

def save_witness_vote(op, block, blockid):
    witness_vote = op.copy()
    query = {
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S"),
        'account': witness_vote['account'],
        'witness': witness_vote['witness']
    }
    witness_vote.update({
        '_ts': datetime.strptime(block['timestamp'], "%Y-%m-%dT%H:%M:%S")
    })
    db.witness_vote.replace_one(query, witness_vote, upsert=True)
    queue_update_account(witness_vote['account'])
    if witness_vote['account'] != witness_vote['witness']:
        queue_update_account(witness_vote['witness'])

mvest_per_account = {}

def load_accounts():
    pprint("[MORPHENE] - Loading all accounts")
    for account in db.account.find():
        if 'vesting_shares' in account:
            mvest_per_account.update({account['name']: account['vesting_shares']})

def queue_update_account(account_name):
    # pprint("Queue Update: " + account_name)
    db.account.update_one({'_id': account_name}, {'$set': {'_dirty': True}}, upsert=True)

def update_account(account_name):
    # pprint("Update Account: " + account_name)
    # Load State
    state = mph.rpc.get_accounts([account_name])
    if not state:
      return
    # Get Account Data
    account = collections.OrderedDict(sorted(state[0].items()))
    # Convert to Numbers
    account['proxy_witness'] = float(account['proxied_vsf_votes'][0]) / 1000000
    for key in ['lifetime_bandwidth', 'to_withdraw']:
        account[key] = float(account[key])
    for key in ['balance', 'vesting_balance', 'vesting_shares', 'vesting_withdraw_rate']:
        account[key] = float(account[key].split()[0])
    # Convert to Date
    for key in ['created','last_account_recovery','last_account_update','last_active_proved','last_owner_proved','last_owner_update','next_vesting_withdrawal']:
        account[key] = datetime.strptime(account[key], "%Y-%m-%dT%H:%M:%S")
    # Combine Savings + Balance
    account['total_balance'] = account['balance']
    # Update our current info about the account
    mvest_per_account.update({account['name']: account['vesting_shares']})
    # Save current state of account
    account['scanned'] = datetime.now()
    if '_dirty' in account:
        del account['_dirty']
    db.account.replace_one({'_id': account_name}, account, upsert=True)

def update_queue():
    # -- Process Queue
    queue_length = 100
    max_date = datetime.now() + timedelta(-3)
    # Don't update if it's been scanned within the six hours
    scan_ignore = datetime.now() - timedelta(hours=6)

    # -- Process Queue - Find 100 previous auctions to update
    queue = db.auction.find({
        'created': {'$gt': max_date},
        'scanned': {'$lt': scan_ignore},
    }).sort([('scanned', 1)]).limit(queue_length)
    pprint("[Queue] Auctions - " + str(queue_length) + " of " + str(queue.count()))
    # for item in queue:
        # update auction

    # -- Process Queue - Find 100 auctions that have past the last payout and need an update
    queue = db.auction.find({
        'end_time': {
          '$lt': datetime.now()
        },
        'mode': {
          '$in': ['first_end_time', 'second_end_time']
        },
        'depth': 0,
        'pending_payout_value': {
          '$gt': 0
        }
    }).limit(queue_length)
    pprint("[Queue] Ended Auctions - " + str(queue_length) + " of " + str(queue.count()))
    # for item in queue:
      # update auction
    # -- Process Queue - Dirty Accounts
    queue_length = 20
    queue = db.account.find({
        '_dirty': True
    }).limit(queue_length)
    pprint("[Queue] Updating Accounts - " + str(queue_length) + " of " + str(queue.count()))
    for item in queue:
        update_account(item['_id'])
    pprint("[Queue] Done")

if __name__ == '__main__':
    pprint("[MorpheneDB] - Starting MorpheneDB Sync Service")
    sys.stdout.flush()
    # Let's find out how often blocks are generated!
    config = mph.rpc.get_config()
    block_interval = config["MORPHENE_BLOCK_INTERVAL"]
    load_accounts()
    # We are going to loop indefinitely
    while True:
        # Update the Queue
        # update_queue()
        # Process New Blocks
        props = mph.rpc.get_dynamic_global_properties()
        block_number = props['last_irreversible_block_num']
        while (block_number - last_block) > 0:
            last_block += 1
            pprint("[MorpheneDB] - Starting Block #" + str(last_block))
            sys.stdout.flush()
            # Get full block
            block = mph.rpc.get_block(last_block)
            # Process block
            process_block(block, last_block)
            # Update our block height
            db.status.update_one({'_id': 'height'}, {"$set" : {'value': last_block}}, upsert=True)
            # if last_block % 100 == 0:
            pprint("[MorpheneDB] - Processed up to Block #" + str(last_block))
            sys.stdout.flush()

        sys.stdout.flush()

        # Sleep for one block
        time.sleep(block_interval)
