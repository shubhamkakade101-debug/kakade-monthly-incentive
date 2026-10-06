from queues import *
checks.clear()
status,d=req.upload(1,field='evidence',confirm=False);check(status==400,'unclassified approval evidence rejected')
status,d=req.upload(1,field='evidence',confirm=True);check(status==200,'classified approval evidence accepted');rid=d['id']
fid=plant.call('detail&id='+str(rid))[1]['files'][0]['id']
headers={'X-Release-Check':os.environ['INCENTIVE_RELEASE_CHECK']} if os.environ.get('INCENTIVE_RELEASE_CHECK') else {}
for c,role in [(plant,'Plant Head'),(admin,'Admin'),(req,'Requester'),(ceo,'CEO'),(hr,'HR Head')]:
 detaildata=c.call('detail&id='+str(rid))[1];check(any(f['id']==fid and f['visibility']=='approval' for f in detaildata['files']),'approval evidence metadata '+role)
 check(c.op.open(urllib.request.Request(BASE+'download&id='+str(fid),headers=headers)).read()==b'Output increased by 12 percent','approval evidence download '+role)
check(plant.call('download&id=1')[0]==403,'legacy private financial file remains restricted')
u=next(u for u in admin.call('state')[1]['users'] if u['username']=='plant');admin.call('user',{**u,'units':[2],'password':''})
check(plant.call('detail&id='+str(rid))[0]==403,'evidence detail unit denial')
try:plant.op.open(urllib.request.Request(BASE+'download&id='+str(fid),headers=headers));check(False,'out of unit evidence denied')
except urllib.error.HTTPError as e:check(e.code==403,'out of unit evidence denied')
admin.call('user',{**u,'units':[1,2,3],'password':''})
# Highest incentive rank must be computed internally; response has no totals or amounts.
rid=req.call('create',{'employee':2,'month':'2026-10','reason':'Synthetic ranking test'})[1]['id']
plant.call('transition',{'id':rid,'decision':'approve'});ceo.call('transition',{'id':rid,'decision':'approve'});hr.call('transition',{'id':rid,'decision':'assign','amount':'99999999'});hr.call('transition',{'id':rid,'decision':'Processed'})
for c,role in [(plant,'Plant Head'),(admin,'Admin'),(req,'Requester'),(ceo,'CEO'),(hr,'HR Head')]:
 top=c.call('state&month=2026-10')[1]['top'];check(top[0]['code']=='DEMO-102','highest monetary total ranks first '+role)
 check(not any(k in r for r in top for k in ['amount','total','sum','value']) and '99999999' not in json.dumps(top),'ranking monetary values withheld '+role)
print(str(len(checks))+' release checks passed\n'+'\n'.join(checks))
