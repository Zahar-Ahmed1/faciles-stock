#!/bin/bash
set -u
API=http://localhost:8000

hr() { echo "──────────────────────────────────────────────────────────"; echo "  $1"; hr2; }
hr2() { echo "──────────────────────────────────────────────────────────"; }
jsonfirst() { python3 -c "import json,sys; d=json.load(sys.stdin); print(list(d.values())[0] if isinstance(d,dict) else d[:1])" 2>/dev/null | head -c 400 ; }
jsonval() { python3 -c "import json,sys; d=json.load(sys.stdin); print(d$1)" 2>/dev/null; }

hr "1. GET /health"
curl -sS "$API/health" -w "\nHTTP:%{http_code}\n" ; hr2

hr "2. POST /auth/login (admin@example.com / password)"
RESP=$(curl -sS -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{"email":"admin@example.com","password":"password"}')
TOKEN=$(echo "$RESP" | jsonval '["data"]["token"]')
echo "$RESP" | head -c 600 ; echo ; echo "TOKEN=$(echo "$TOKEN" | head -c 30)..."

hr "3. GET /auth/me  (sans token -> 401 attendu)"
curl -sS "$API/auth/me" -w "\nHTTP:%{http_code}\n" -o /tmp/out -w "%{http_code} -> "; head -c 250 /tmp/out; echo

hr "4. GET /auth/me  (avec token)"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/auth/me" | python3 -m json.tool | head -20; hr2

hr "5. GET /clients (ADMIN -> 200)"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/clients" -w "\nHTTP:%{http_code}\n" | python3 -c "import json,sys; d=json.load(sys.stdin); print('success:',d['success']); items=d['data']; print('count:', len(items)); [print(' ',c['id'],c['name'],c['code']) for c in items[:10]]"

hr "6. GET /clients/{oracleId}/sites"
ORACLE_ID=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/clients" | python3 -c "import json,sys;d=json.load(sys.stdin); print([c['id'] for c in d['data'] if c['code']=='ORACLE'][0])")
echo "Oracle client id=$ORACLE_ID"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/clients/$ORACLE_ID/sites" -w "\nHTTP:%{http_code}\n" | python3 -c "import json,sys;d=json.load(sys.stdin);print('success:',d['success']); [print(' ',s['id'],s['name']) for s in d['data'][:10]]"

hr "7. GET /products (13 produits attendus)"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/products" | python3 -c "import json,sys;d=json.load(sys.stdin); print('success:',d['success'],'count:',len(d['data'])); [print(' ',p['id'],p['name']) for p in d['data'][:10]]"

hr "8. GET /stocks/site/{oracle_rabat_site} (13 stocks attendus)"
RABAT_SITE_ID=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/clients/$ORACLE_ID/sites" | python3 -c "import json,sys;d=json.load(sys.stdin); print([s['id'] for s in d['data'] if 'Rabat' in s['name']][0])")
echo "Rabat site id=$RABAT_SITE_ID"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/stocks/site/$RABAT_SITE_ID" | python3 -c "
import json,sys
d=json.load(sys.stdin)
print('success:',d['success'],'count:',len(d['data']))
for s in d['data'][:8]:
    print(f\"  #{s['id']:>2}  qty={s['quantity']:>3}  min={s['minimumQuantity']:>2}  {s['status']:>12}  {s['productName']}\")
"

hr "9. POST /stocks/{id}/decrement -> puis /increment (stock card eau 1.5L Rabat)"
STOCK_ID=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/stocks/site/$RABAT_SITE_ID" | python3 -c "import json,sys;d=json.load(sys.stdin);print([s['id'] for s in d['data'] if 'Eau minérale 1.5L' in s['productName']][0])")
echo "Stock Eau 1.5L Rabat id=$STOCK_ID"
BEFORE=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/stocks/$STOCK_ID" | python3 -c "import json,sys;d=json.load(sys.stdin);print(d['data']['quantity'])")
echo "AVANT decrement -> quantity=$BEFORE"
echo " decrement:"
curl -sS -X POST -H "Authorization: Bearer $TOKEN" "$API/stocks/$STOCK_ID/decrement" | python3 -m json.tool | head -15
echo " increment (back to original):"
curl -sS -X POST -H "Authorization: Bearer $TOKEN" "$API/stocks/$STOCK_ID/increment" | python3 -c "import json,sys;d=json.load(sys.stdin); print(d['success'] and d['data']['quantity']==$BEFORE, '-> finalQty=', d['data']['quantity'], 'status=', d['data']['status'])"

hr "10. Forcer OUT_OF_STOCK en decrementant jusqua 0 puis depasser (409 attendu)"
LOW_ID=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/stocks/site/$RABAT_SITE_ID" | python3 -c "import json,sys;d=json.load(sys.stdin);
l=[s for s in d['data'] if s['quantity']<=10]
print(l[0]['id'])")
echo "Picking low stock id=$LOW_ID for OUT_OF_STOCK test"
QTY=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/stocks/$LOW_ID" | python3 -c "import json,sys;d=json.load(sys.stdin);print(d['data']['quantity'])")
echo "Quantité initiale=$QTY"
for ((i=0;i<QTY+3;i++)); do
    echo -n "  essai #$((i+1)): "
    CODE=$(curl -sS -o /tmp/out -w "%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" "$API/stocks/$LOW_ID/decrement")
    echo -n "HTTP $CODE "
    python3 -c "import json,sys;d=json.load(open('/tmp/out'));
print(d['success'] and ('qty='+str(d['data']['quantity'])) or ('ERR '+str(d['error']['code'])) )" 2>/dev/null
done

hr "11. POST /stocks/{id}/decrement/5  (admin decrement qty=5)"
BIG_ID=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/stocks/site/$RABAT_SITE_ID" | python3 -c "import json,sys;d=json.load(sys.stdin);l=[s for s in d['data'] if s['quantity']>=20]; print(l[0]['id'])")
echo "Picking id=$BIG_ID"
curl -sS -X POST -H "Authorization: Bearer $TOKEN" "$API/stocks/$BIG_ID/decrement/5" | python3 -c "import json,sys;d=json.load(sys.stdin); print(d['success'] and ('oldQty='+str(d['data']['oldQuantity'])+' newQty='+str(d['data']['quantity'])+' alertCreated='+str(d['data']['alertCreated'])+' movementId='+str(d['data']['movementId'])))"; echo "HTTP:OK payload above"

hr "12. GET /stock-movements/site/$RABAT_SITE_ID (derniers 3)"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/stock-movements/site/$RABAT_SITE_ID" | python3 -c "
import json,sys;d=json.load(sys.stdin)
print('total movements returned:', len(d['data']))
for m in d['data'][:3]:
    print(f\"  #{m['id']}  type={m['type']:>10}  qty={m['quantity']:>3}  before->after = {m['stockBefore']}->{m['stockAfter']}  by {m['userFirstName']} {m['userLastName']}  reason={m['reason']}\")"

hr "13. GET /alerts/unread (global)"
curl -sS -H "Authorization: Bearer $TOKEN" "$API/alerts/unread" | python3 -c "
import json,sys;d=json.load(sys.stdin)
print('count unread:',len(d['data']))
for a in d['data'][:6]:
    print(f\"  #{a['id']} status={a['status']:>8}  type={a['type']:>13}  qty@alert={a['quantityAtAlert']} threshold={a['threshold']}\")"

hr "14. Role gate : tenter STOCK_MANAGER (Oracle Rabat) sur GET /stocks/site IAM Agadir -> 403 attendu"
SM_RESP=$(curl -sS -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{"email":"stock.manager@example.com","password":"password"}')
SM_TOK=$(echo "$SM_RESP" | python3 -c "import json,sys;print(json.load(sys.stdin)['data']['token'])")
IAM_AGADIR_ID=$(curl -sS -H "Authorization: Bearer $TOKEN" "$API/clients" > /dev/null ; curl -sS -H "Authorization: Bearer $TOKEN" "$API/clients/$ORACLE_ID/sites" > /dev/null ; curl -sS -H "Authorization: Bearer $TOKEN" "$API/sites" | python3 -c "import json,sys;d=json.load(sys.stdin);print([s['id'] for s in d['data'] if 'Agadir' in s['name']][0])")
echo "IAM Agadir id=$IAM_AGADIR_ID  (STOCK_MANAGER Oracle Rabat -> forbidden expected)"
curl -sS -w "\nHTTP:%{http_code}\n" -H "Authorization: Bearer $SM_TOK" "$API/stocks/site/$IAM_AGADIR_ID"

hr "FIN DES TESTS"
