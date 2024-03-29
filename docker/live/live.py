from autobahn.twisted.websocket import WebSocketServerFactory, \
    WebSocketServerProtocol, \
    listenWS
from datetime import datetime, timedelta
from morphenepython import MorpheneClient
from pprint import pprint
from twisted.internet import reactor
from twisted.python import log
from collections import Counter

import json
import math
import sys
import os
import re

fullnodes = [
    'https://morphene.io/rpc',
]
mph = MorpheneClient(fullnodes)

class BroadcastServerProtocol(WebSocketServerProtocol):

    def onOpen(self):
        self.factory.register(self)

    def onMessage(self, payload, isBinary):
        if not isBinary:
            self.factory.subscribe(self, payload.decode('utf8'))

    def connectionLost(self, reason):
        WebSocketServerProtocol.connectionLost(self, reason)
        self.factory.unregister(self)


class BroadcastServerFactory(WebSocketServerFactory):

    """
    Simple broadcast server broadcasting any message it receives to all
    currently connected clients.
    """

    def __init__(self, url):
        WebSocketServerFactory.__init__(self, url)
        props = mph.rpc.get_dynamic_global_properties()
        self.clients = []
        self.channels = {}
        self.tickcount = 0
        self.last_block = props['head_block_number']
        self.last_block_processed = props['last_irreversible_block_num']
        self.mentions = re.compile(r"([@])(\w+)\b")
        self.tick()

    def tick(self):
        props = mph.rpc.get_dynamic_global_properties()
        state = mph.rpc.get_state('@initwitness')
        irreversible = props['last_irreversible_block_num']

        if props['head_block_number'] != self.last_block:
            self.last_block = props['head_block_number']
            # print("new block {}".format(self.last_block))
            self.publishProps(props)
            self.publishState(state)

        while (irreversible - self.last_block_processed) > 0:
            self.last_block_processed += 1
            # publish operation events to subscribers
            # print("processing block {} [{}/{}/{}]".format(self.last_block_processed, len(self.clients), len(self.channels), sum(len(v) for v in self.channels.values())))
            self.publishBlock(self.last_block_processed)
            # self.publishOps(self.last_block_processed)

        reactor.callLater(1, self.tick)

    def publishProps(self, props):
        total_vesting_fund_morph = float(props['total_vesting_fund_morph'].split(" ")[0])
        total_vesting_shares = float(props['total_vesting_shares'].split(" ")[0])
        props['morph_per_mvests'] = math.floor(total_vesting_fund_morph / total_vesting_shares * 1000000 * 1000) / 1000
        props['reversible_blocks'] = props['head_block_number'] - props['last_irreversible_block_num']
        self.publish("props", "props", props)

    def publishState(self, state):
        partial = {
          'witness_schedule': state['witness_schedule']
        }
        self.publish("state", "state", partial)

    def publishBlock(self, height):
        block = mph.rpc.get_block(height)
        data = {
            'height': height,
            'accounts': set([]),
            'opCount': 0,
            'opCount': 0,
            'opTypes': [],
            'ts': block['timestamp'],
        }
        if block['transactions']:
            for tx in block['transactions']:
                for op in tx['operations']:
                    opType = op['type'].replace("_operation","")
                    data['opCount'] += 1
                    data['opTypes'].append(opType)
                    for account in self.getRelatedAccounts(opType, op['value']):
                        data['accounts'].add(account)

        data['opCounts'] = Counter(data['opTypes'])
        data['accounts'] = list(data['accounts'])
        self.publish("blocks", "block", data)

    def publishOps(self, block):
        ops = mph.rpc.get_ops_in_block(block, False)
        for op in ops:
            opType = op['op'][0]
            opData = op['op'][1]
            for account in self.getRelatedAccounts(opType, opData):
                channel = "@{}".format(account)
                self.publish(channel, opType, op)

    # retrieves list of related accounts based on op type
    def getRelatedAccounts(self, opType, opData):
        accounts = set([])
        fieldMap = {
            'account_create':           [],
            'account_update':           [],
            'account_witness_vote':     ['account', 'witness'],
            'fill_vesting_withdraw':    [],
            'pow':                      [],
            'transfer':                 [],
            'transfer_to_vesting':      [],
        }
        if opType in fieldMap.keys():
            for field in fieldMap[opType]:
                accounts.add(opData[field])

        return accounts

    def register(self, client):
        if client not in self.clients:
            # print("registered client [{}]".format(client.peer))
            self.subscribe(client, "blocks")
            self.subscribe(client, "props")
            self.subscribe(client, "state")
            for x in range(1, 11):
              previous = self.last_block_processed - 10 + x
              self.publishBlock(previous)
            self.clients.append(client)

    def unregister(self, client):
        if client in self.clients:
            # print("unregistered client [{}]".format(client.peer))
            self.clients.remove(client)

    def broadcast(self, msg):
        # print("broadcasting message '[{}]' ..".format(msg))
        for c in self.clients:
            c.sendMessage(msg.encode('utf8'))

    def subscribe(self, client, channel):
        # print("subscribed client [{}] to channel [{}]".format(client.peer, channel))
        # Create channel if it doesn't exist
        if channel not in self.channels:
            self.channels[channel] = set([])
        # Add client to channel if it isn't already subscribed
        if client not in self.channels[channel]:
            self.channels[channel].add(client)

    def publish(self, channel, opType, opData):
        if channel in self.channels:
            for c in self.channels[channel]:
                data = json.dumps({opType: opData})
                # print("publishing op '{}' [{}] to subscriber [{}] based on channel subscription [{}]".format(opType, data, c.peer, channel))
                c.sendMessage(data.encode('utf8'))

if __name__ == '__main__':

    log.startLogging(sys.stdout)

    ServerFactory = BroadcastServerFactory

    factory = ServerFactory(u"ws://127.0.0.1:8888")
    factory.protocol = BroadcastServerProtocol
    listenWS(factory)

    reactor.run()
