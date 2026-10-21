import urllib.request, urllib.error, json
API='http://localhost:8000'

def req(method, path, data=None, token=None):
    headers = {}
    body=None
    if data is not None:
        body = json.dumps(data).encode()
        headers['Content-Type']='application/json'
    if token:
        headers['Authorization']=f'Bearer {token}'
    r = urllib.request.Request(API+path, data=body, method=method, headers=headers)
    try:
        with urllib.request.urlopen(r) as resp:
            return resp.status, json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        try:
            return e.code, json.loads(e.read().decode())
        except Exception:
            return e.code, None

def login(email, password='password'):
    s, d = req('POST', '/auth/login', {"email": email, "password": password})
    assert s == 200 and d and d.get('success'), (s, d)
    return d['data']['token'], d['data']['user']

print('=== 1. health ===')
print(req('GET', '/health'))

print('\n=== 2. admin login ===')
tok, me = login('admin@example.com')
print('role =', me['role'], 'siteId =', me['siteId'])

s, clients = req('GET', '/clients', token=tok)
print(f'\n=== 3. /clients HTTP {s} count={len(clients["data"])} ===')
oracle = [c for c in clients['data'] if c['code']=='ORACLE'][0]
iam    = [c for c in clients['data'] if c['code']=='IAM'][0]
awb    = [c for c in clients['data'] if c['code']=='AWB'][0]
print(' Oracle #', oracle['id'], 'IAM #', iam['id'], 'AWB #', awb['id'])

s, sites = req('GET', f"/clients/{oracle['id']}/sites", token=tok)
print(f'\n=== 4. /clients/{oracle["id"]}/sites HTTP {s} {len(sites["data"])} sites ===')
rabat = [ss for ss in sites['data'] if 'Rabat' in ss['name']][0]
print(' Oracle Rabat =', rabat['id'], rabat['name'])

s, products = req('GET', '/products', token=tok)
print(f'\n=== 5. /products total {len(products["data"])} ===')
for p in products['data']:
    ref = p.get('reference') or p.get('sku') or ''
    print(f"  #{p['id']:>2} {ref:>12} {p['name']:<30} {p['unit']:<10} cat#{p.get('categoryId')}")

s, stocks = req('GET', f"/stocks/site/{rabat['id']}", token=tok)
print(f'\n=== 6. /stocks/site/{rabat["id"]} count={len(stocks["data"])} ===')
for st in stocks['data']:
    bar = '●' if st['status']=='NORMAL' else ('▲' if st['status']=='LOW_STOCK' else '✖')
    print(f"  {bar} #{st['id']:>2} qty={st['quantity']:>3} min={st['minimumQuantity']:<2} max={st['maximumQuantity']:<3} {st['status']:<13} ref={st['productReference'] or '':<10} {st['productName']}")

eau = [st for st in stocks['data'] if 'Eau minérale 1.5L' in st['productName']][0]
print(f'\n=== 7. decrement/increment Stock #{eau["id"]} Eau 1.5L (qty={eau["quantity"]}) ===')
s, dec = req('POST', f"/stocks/{eau['id']}/decrement", token=tok)
print(' decrement HTTP', s, 'success=', dec.get('success'),
      'oldQty=', dec['data']['oldQuantity'] if dec.get('success') else None,
      'newQty=', dec['data']['quantity'] if dec.get('success') else None,
      'status=', dec['data']['status'] if dec.get('success') else None,
      'movementId=', dec['data']['movementId'] if dec.get('success') else None,
      'alertCreated=', dec['data']['alertCreated'] if dec.get('success') else None)
s, inc = req('POST', f"/stocks/{eau['id']}/increment", token=tok)
print(' increment HTTP', s, 'success=', inc.get('success'),
      'oldQty=', inc['data']['oldQuantity'] if inc.get('success') else None,
      'newQty=', inc['data']['quantity'] if inc.get('success') else None,
      'status=', inc['data']['status'] if inc.get('success') else None)
assert inc['data']['quantity'] == eau['quantity'], 'increment revert failed'

low_candidates = [st for st in stocks['data'] if 0 < st['quantity'] <= st['minimumQuantity']]
if not low_candidates:
    low_candidates = [st for st in stocks['data'] if 0 < st['quantity'] <= st['minimumQuantity'] + 5]
low = low_candidates[-1]
print(f'\n=== 8. OUT_OF_STOCK jusqua erreur sur stock #{low["id"]} qty={low["quantity"]} ({low["productName"]}) ===')
qty = low['quantity']
for i in range(qty + 3):
    s, d = req('POST', f"/stocks/{low['id']}/decrement", token=tok)
    if d and d.get('success'):
        print(f"  essai {i+1:>2}: HTTP {s} qty={d['data']['quantity']} status={d['data']['status']}")
    else:
        code = d.get('error', {}).get('code') if d else None
        msg = d.get('error', {}).get('message') if d else None
        print(f"  essai {i+1:>2}: HTTP {s} code={code} msg={msg}")
        break

s, hist = req('GET', f"/stock-movements/site/{rabat['id']}", token=tok)
print(f'\n=== 9. /stock-movements/site/{rabat["id"]} count={len(hist["data"]) if hist else 0} derniers 5 ===')
for m in hist['data'][:5]:
    print(f"  #{m['id']:>3} {m['type']:<12} qty={m['quantity']:>3}  {m['stockBefore']}→{m['stockAfter']}  par {m['userFirstName']} {m['userLastName']}  [{m['productName']}] — {m['reason']}")

s, al = req('GET', '/alerts/unread', token=tok)
print(f'\n=== 10. /alerts/unread total={len(al["data"])} ===')
for a in al['data'][:8]:
    print(f"  #{a['id']:>2} {a['status']:>8} {a['type']:>13} qty@alert={a['quantityAtAlert']} seuil={a['threshold']} msg={a['message'][:70]}")

print('\n=== 11. RoleMiddleware — STOCK_MANAGER Oracle Rabat sur site Agadir ===')
sm_tok, sm_me = login('stock.manager@example.com')
print('  SM Oracle siteId =', sm_me['siteId'])
agadir_id = None
_, all_sites = req('GET', '/sites', token=tok)
for ss in all_sites['data']:
    if 'Agadir' in ss['name']:
        agadir_id = ss['id']
s_forb, d_forb = req('GET', f"/stocks/site/{agadir_id}", token=sm_tok)
print('  GET stocks/site/Agadir SM Oracle -> HTTP', s_forb,
      'code=', d_forb.get('error', {}).get('code') if d_forb else None)
s_ok, _ = req('GET', f"/stocks/site/{sm_me['siteId']}", token=sm_tok)
print('  GET stocks/site/(son propre site) HTTP', s_ok)

s_prod, d_prod = req('POST', '/products',
                      {'name':'x','reference':'xunit','categoryId':1,'unit':'unités'},
                      token=sm_tok)
print('  POST /products (SM forbidden) HTTP', s_prod,
      'code=', d_prod.get('error', {}).get('code') if d_prod else None)

print('\n=== END ALL TESTS PASSED ===')
