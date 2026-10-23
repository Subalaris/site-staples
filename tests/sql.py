"""Run the actual prepared revision query in an isolated SQL database."""
import json
import sqlite3
import sys
payload = json.load(sys.stdin)
db = sqlite3.connect(':memory:')
db.execute('CREATE TABLE posts (ID INTEGER PRIMARY KEY, post_parent INTEGER, post_type TEXT, post_status TEXT, post_name TEXT)')
db.execute('CREATE TABLE options (option_name TEXT)')
for name in payload.get('options', []):
    db.execute('INSERT INTO options VALUES (?)', [name])
for post in payload['posts']:
    db.execute('INSERT INTO posts VALUES (?, ?, ?, ?, ?)', [post.get(k, 0 if k in ('ID', 'post_parent') else '') for k in ('ID', 'post_parent', 'post_type', 'post_status', 'post_name')])
print(json.dumps([r[0] for r in db.execute(payload['sql'])]))
