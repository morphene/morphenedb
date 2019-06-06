db.createCollection("block_30d", {capped: true, max: 864000, size: 2147483648})

db.block.createIndex({witness: 1, _ts: 1})
db.block_30d.createIndex({witness: 1, _ts: 1})

db.account.createIndex({name: 1});
db.account.createIndex({created: 1});
db.account.createIndex({vesting_shares: 1});
db.account.createIndex({witness_votes: 1});
db.account.createIndex({name: 1, vesting_shares: 1});

db.account_history.createIndex({date: 1, name: 1});
