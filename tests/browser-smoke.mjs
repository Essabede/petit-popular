// Uses the disposable WP/TEC contract fixtures, not production data or a live TEC instance.
import {spawn} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {readFileSync,existsSync,mkdirSync,writeFileSync} from 'node:fs';
const profile='/tmp/petit-browser-040-'+Date.now();mkdirSync(profile);
const child=spawn('/usr/bin/chromium',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--disable-background-networking','--disable-extensions','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:['ignore','ignore','pipe']});
let log='';child.stderr.on('data',x=>{log=(log+x).slice(-2500)});
try {
 const start=Date.now();while(!existsSync(profile+'/DevToolsActivePort')){if(Date.now()-start>9000)throw Error('Chromium startup timed out: '+log);await new Promise(r=>setTimeout(r,100));}
 const port=readFileSync(profile+'/DevToolsActivePort','utf8').split('\n')[0];
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();
 const ws=new WebSocket(tabs[0].webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject});let next=0;const pending=new Map();ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&pending.has(m.id)){const [r,j]=pending.get(m.id);pending.delete(m.id);m.error?j(Error(m.error.message)):r(m.result)}};
 const send=(method,params={})=>new Promise((r,j)=>{const id=++next;pending.set(id,[r,j]);ws.send(JSON.stringify({id,method,params}))});
 await send('Page.enable');
 const root=fileURLToPath(new URL('..', import.meta.url)).replace(/\/$/,'');
 const markup=readFileSync(root+'/tests/admin-fixture.html','utf8');
 const css=readFileSync(root+'/assets/admin.css','utf8');
 const popular=readFileSync(root+'/assets/popular.css','utf8');
 const preview=readFileSync(root+'/tests/content-fixture.html','utf8');
 const html='<!doctype html><html lang="ca"><head><style>body{margin:0;padding:28px;font:14px Arial;background:#f0f0f1;color:#1d2327}select,input,button{font:inherit;padding:7px}button{cursor:pointer}.button{border:1px solid #8c8f94;background:white;border-radius:4px}textarea{box-sizing:border-box}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px)}'+css+'</style></head><body>'+markup+'</body></html>';
 await send('Runtime.evaluate',{expression:'document.open();document.write('+JSON.stringify(html)+');document.close();'});
 const setup=`window.PetitPopularBuilder={url:'/test-only',nonce:'test-only',styles:'data:text/css,'+encodeURIComponent(${JSON.stringify(popular)}),defaults:Object.fromEntries([...document.querySelectorAll('[data-pp]')].map(e=>[e.dataset.pp,e.value])),saved:[],categories:{posts:[],events:[]}};window.fetch=async()=>({ok:true,json:async()=>({success:true,data:{html:${JSON.stringify(preview)},diagnostics:[]}})});`;
 await send('Runtime.evaluate',{expression:setup+readFileSync(root+'/assets/builder.js','utf8')});
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
 await send('Runtime.evaluate',{expression:"document.getElementById('pp-type').value='events';document.getElementById('pp-type').dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('pp-event_days').value='30';document.getElementById('pp-event_days').dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('pp-preview-mode').value='content';document.getElementById('pp-preview-mode').dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('pp-builder').scrollIntoView();"});
 await new Promise(r=>setTimeout(r,1200));
 const result=await send('Runtime.evaluate',{expression:"({summary:document.getElementById('pp-filter-summary').textContent,frame:document.getElementById('pp-preview-frame').contentDocument.body.textContent,visible:!document.getElementById('pp-event-date-group').hidden,overflow:document.documentElement.scrollWidth>innerWidth})",returnByValue:true});
 const value=result.result.value;if(value.overflow){const debug=await send('Runtime.evaluate',{expression:"[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,12).map(e=>({tag:e.tagName,id:e.id,cls:e.className,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width}))",returnByValue:true});console.log(JSON.stringify(debug.result.value));}if(!value.visible||!value.frame.includes('slime')||value.overflow)throw Error('Unexpected browser state: '+JSON.stringify(value));
 const shot=await send('Page.captureScreenshot',{format:'png'});writeFileSync(root+'/tests/admin-browser.png',Buffer.from(shot.data,'base64'));
 console.log('PASS: Chromium event form, real-content preview fixture and desktop layout');console.log(JSON.stringify(value));ws.close();
} finally {child.kill('SIGTERM');}
