# Run only against a separate synthetic test storage / preview.
from workflow import *
checks.clear()
from urllib.parse import quote
roles={'Admin':admin,'Requester':req,'Plant Head':plant,'CEO':ceo,'HR Head':hr}
expected={'Admin':['Request','Plant Head','CEO','HR Value','HR Processing','Processed','Not Processed'],'Requester':['Request','Processed','Not Processed'],'Plant Head':['Plant Head'],'CEO':['CEO'],'HR Head':['HR Value','HR Processing','Processed','Not Processed']}
allstages=expected['Admin']
# Fixtures for every stage in each of the three units.
for employee in [1,2,3]:
 for stage in ['Plant Head','CEO','HR Value','HR Processing','Processed','Not Processed']:
  rid=req.call('create',{'employee':employee,'month':'2026-10','reason':'Synthetic queue fixture '+stage})[1]['id']
  if stage=='Plant Head':continue
  plant.call('transition',{'id':rid,'decision':'approve'})
  if stage=='CEO':continue
  ceo.call('transition',{'id':rid,'decision':'approve'})
  if stage=='HR Value':continue
  hr.call('transition',{'id':rid,'decision':'assign','amount':'7654.32'})
  if stage in ['Processed','Not Processed']:hr.call('transition',{'id':rid,'decision':stage})
admin.call('user',{'username':'other-demo','name':'Other Demo Requester','password':'DemoOnly!2026','role':'Requester','units':[1,2,3],'active':1,'reports':0})
other=Client('other-demo')
otherids=[]
for emp in [1,2,3]:otherids.append(other.call('create',{'employee':emp,'month':'2026-10','reason':'Other synthetic requester'})[1]['id'])
users={u['role']:u for u in admin.call('state')[1]['users'] if u['username']!='other-demo'}
for role,c in roles.items():
 for unit in [1,2,3]:
  u=users[role];payload={**u,'units':[unit],'reports':1,'password':''}
  check(admin.call('user',payload)[0]==200,f'configure {role} unit {unit}')
  s=c.call('state')[1];check([x['key'] for x in s['stages']]==expected[role],f'allowed stages {role} unit {unit}')
  for stage in allstages+['Rejected','unknown']:
   status,d=c.call('queue&stage='+quote(stage))
   if stage not in expected[role]:check(status==403 and list(d)==['error'],f'forbidden queue {role} {stage} unit {unit}');continue
   rows=d['requests'];check(status==200 and all(r['unit']==unit for r in rows),f'unit filtered {role} {stage} {unit}')
   if stage!='Request':check(all(r['status']==stage for r in rows),f'exact stage {role} {stage} {unit}')
   if role=='Requester':check(not any(r['id'] in otherids for r in rows),f'own tracking despite report grant {stage} {unit}')
   if role not in ['CEO','HR Head']:check('"amount"' not in json.dumps(d) and '765432' not in json.dumps(d),f'non monetary queue {role} {stage} {unit}')
   summary=next(x for x in s['stages'] if x['key']==stage)
   n=len(rows) if stage!='Request' else sum(r['status'] in ['Plant Head','CEO','HR Value','HR Processing'] for r in rows)
   check(summary['count']==n,f'correct authorized count {role} {stage} {unit}')
  admin.call('user',{**u,'units':[1,2,3],'password':''})
# Report access does not permit processing actions, even with readable details.
rid=req.call('create',{'employee':1,'month':'2026-10','reason':'Report permission test'})[1]['id']
for c in [admin,req,ceo,hr]:check(c.call('transition',{'id':rid,'decision':'approve'})[0]==403,'report grant cannot skip role or stage')
# Permissions are freshly checked rather than cached in the signed-in session.
u=users['Plant Head'];admin.call('user',{**u,'role':'Requester','units':[1,2,3],'password':''})
check(plant.call('queue&stage=Plant%20Head')[0]==403,'role change revokes queue immediately')
admin.call('user',{**u,'units':[1,2,3],'password':''})
print(str(len(checks))+' queue checks passed\n'+'\n'.join(checks))
