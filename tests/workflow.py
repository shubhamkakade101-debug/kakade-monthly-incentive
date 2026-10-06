import urllib.request,urllib.error,http.cookiejar,json,uuid,os
BASE=os.environ.get('INCENTIVE_TEST_URL','http://127.0.0.1:8765')+'/api.php?action='
checks=[]
def check(v,label):
 assert v,label
 checks.append(label)
class Client:
 def __init__(self,name=None):
  self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  self.csrf=''
  self.csrf=self.call('session')[1]['csrf']
  if name: check(self.call('login',{'username':name,'password':'DemoOnly!2026'})[0]==200,'login '+name)
 def call(self,a,data=None,token=True):
  h={'X-CSRF-Token':self.csrf} if token else {}
  if os.environ.get('INCENTIVE_RELEASE_CHECK'):h['X-Release-Check']=os.environ['INCENTIVE_RELEASE_CHECK']
  if data is not None:h['Content-Type']='application/json'
  r=urllib.request.Request(BASE+a,data=json.dumps(data).encode() if data is not None else None,headers=h)
  try:res=self.op.open(r)
  except urllib.error.HTTPError as e:res=e
  body=res.read().decode()
  return res.status,json.loads(body) if not a.startswith('export') else body
 def upload(self,employee,content=b'Output increased by 12 percent',filename='evidence.txt',field='file',confirm=False):
  boundary='----'+uuid.uuid4().hex
  fields={'employee':str(employee),'month':'2026-10','reason':'Synthetic workflow test achievement'}
  if confirm:fields['evidence_confirm']='1'
  b=b''
  for k,v in fields.items():b+=f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
  b+=f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\nContent-Type: text/plain\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode()
  r=urllib.request.Request(BASE+'create',data=b,headers={'X-CSRF-Token':self.csrf,'Content-Type':'multipart/form-data; boundary='+boundary,**({'X-Release-Check':os.environ['INCENTIVE_RELEASE_CHECK']} if os.environ.get('INCENTIVE_RELEASE_CHECK') else {})})
  try:res=self.op.open(r)
  except urllib.error.HTTPError as e:res=e
  body=res.read()
  try:return res.status,json.loads(body)
  except:print(body.decode());raise
admin,req,plant,ceo,hr=[Client(n) for n in ['admin','requester','plant','ceo','hr']]
check(Client().call('state')[0]==401,'anonymous denied')
check(req.call('create',{},False)[0]==403,'CSRF enforced')
code,d=req.upload(1);check(code==200,'multipart upload and creation');rid=d['id']
check(ceo.call('transition',{'id':rid,'decision':'approve'})[0]==403,'CEO cannot skip Plant')
check(hr.call('transition',{'id':rid,'decision':'assign','amount':'9876.54'})[0]==403,'HR cannot skip approval')
check(req.call('transition',{'id':rid,'decision':'approve'})[0]==403,'Requester cannot approve')
for client,decision,extra in [(plant,'approve',{}),(ceo,'approve',{}),(hr,'assign',{'amount':'9876.54'}),(hr,'Processed',{})]:
 check(client.call('transition',{'id':rid,'decision':decision,**extra})[0]==200,'stage '+decision)
check(hr.call('transition',{'id':rid,'decision':'Processed'})[0]==403,'terminal request immutable')
for c,name in [(admin,'admin'),(req,'requester'),(plant,'plant')]:
 for action in ['state','detail&id='+str(rid)]:
  status,d=c.call(action);check(status==200 and '"amount"' not in json.dumps(d) and '987654' not in json.dumps(d),name+' amount absent '+action)
 check(c.call('download&id=1')[0]==403,name+' attachment denied')
check('987654' not in admin.call('export')[1],'Admin export amount absent')
check(req.call('export')[0]==403,'Report denied without grant')
check(hr.call('detail&id='+str(rid))[1]['request']['amount']==987654,'HR exact cents visible')
check(ceo.call('detail&id='+str(rid))[1]['request']['amount']==987654,'CEO amount visible')
d=req.call('detail&id='+str(rid))[1];check(len(d['audit'])==5,'five audited stages');check(not d['files'],'Requester file metadata absent')
fileid=hr.call('detail&id='+str(rid))[1]['files'][0]['id']
r=hr.op.open(urllib.request.Request(BASE+'download&id='+str(fileid),headers=({'X-Release-Check':os.environ['INCENTIVE_RELEASE_CHECK']} if os.environ.get('INCENTIVE_RELEASE_CHECK') else {})));check(r.read()==b'Output increased by 12 percent','private file round trip')
check(req.upload(1,b'<?php echo 1;','evil.php')[0]==400,'executable upload blocked')
check(req.upload(1,b'not a pdf','fake.pdf')[0]==400,'mismatched file blocked')
users=admin.call('state')[1]['users'];requester=next(u for u in users if u['username']=='requester');grant={**requester,'units':[1],'reports':1,'active':1,'password':''}
check(admin.call('user',grant)[0]==200,'Admin grants reports and unit scope')
check('amount' not in req.call('export')[1],'Granted report remains non monetary')
check(req.call('detail&id=2')[0]==403,'out of unit detail denied')
check(req.call('create',{'employee':2,'month':'2026-10','reason':'test'})[0]==403,'out of unit submission denied')
check(plant.call('user',grant)[0]==403,'non Admin user access denied')
check(req.call('employee',{'code':'X','name':'X','unit':1,'department':1})[0]==403,'non Admin employee management denied')
check(admin.call('user',{**grant,'units':[1,2,3],'reports':0})[0]==200,'restore demonstration access')
check(admin.call('department',{'name':'Test Department '+uuid.uuid4().hex[:6]})[0]==200,'department persistence')
check(admin.call('employee',{'name':'Demo Test Employee','code':'TEST-'+uuid.uuid4().hex[:6],'unit':3,'department':1})[0]==200,'employee persistence')
for final in ['Not Processed','Rejected']:
 rid=req.call('create',{'employee':1,'month':'2026-10','reason':'Synthetic '+final+' test'})[1]['id']
 if final=='Rejected':check(plant.call('transition',{'id':rid,'decision':'reject'})[0]==200,'Plant rejection')
 else:
  plant.call('transition',{'id':rid,'decision':'approve'});ceo.call('transition',{'id':rid,'decision':'approve'})
  check(hr.call('transition',{'id':rid,'decision':'assign','amount':'-1'})[0]==400,'negative value denied')
  hr.call('transition',{'id':rid,'decision':'assign','amount':'100'});check(hr.call('transition',{'id':rid,'decision':final})[0]==200,'Not Processed outcome')
 check(req.call('detail&id='+str(rid))[1]['request']['received'] is None,'no received date '+final)
hruser=next(u for u in admin.call('state')[1]['users'] if u['username']=='hr')
admin.call('user',{**hruser,'units':[1,2,3],'reports':1,'password':''})
check('amount_inr' in hr.call('export')[1] and '9876.54' in hr.call('export')[1],'HR report INR conversion')
admin.call('user',{**hruser,'units':[1,2,3],'reports':0,'password':''})
for c in [admin,req,plant,ceo,hr]:check('amount' not in json.dumps(c.call('state&month=2026-10')[1]['top']),'dashboard excludes amounts '+c.call('session')[1]['user']['role'])
print(str(len(checks))+' checks passed\n'+'\n'.join(checks))
