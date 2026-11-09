// Browser layout check with disposable WordPress-rendered fixtures, never production data.
import {spawn} from 'node:child_process';
import {readFileSync,existsSync,mkdirSync,writeFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('..',import.meta.url)).replace(/\/$/,'');
const profile='/tmp/petit-density-browser-'+Date.now();mkdirSync(profile);
const chrome=spawn('/usr/bin/chromium',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore'});
try {
 const start=Date.now();while(!existsSync(profile+'/DevToolsActivePort')){if(Date.now()-start>9000)throw Error('Chromium startup timed out');await new Promise(r=>setTimeout(r,100));}
 const port=readFileSync(profile+'/DevToolsActivePort','utf8').split('\n')[0];
 const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();const ws=new WebSocket(tabs[0].webSocketDebuggerUrl);
 await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j});let next=0;const pending=new Map();
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id&&pending.has(m.id)){const[r,j]=pending.get(m.id);pending.delete(m.id);m.error?j(Error(m.error.message)):r(m.result)}};
 const send=(method,params={})=>new Promise((r,j)=>{const id=++next;pending.set(id,[r,j]);ws.send(JSON.stringify({id,method,params}))});
 await send('Page.enable');await send('DOM.enable');await send('CSS.enable');
 await send('Emulation.setDeviceMetricsOverride',{width:1050,height:1100,deviceScaleFactor:1,mobile:false});
 const css=readFileSync(root+'/assets/popular.css','utf8');
 const samples=['comfortable','compact','tight'].map(mode=>`<section id="${mode}" class="sample"><h2>${mode}</h2>${readFileSync(root+`/tests/density-${mode}-fixture.html`,'utf8')}</section>`).join('');
 const html=`<!doctype html><html><head><style>body{margin:20px;display:flex;gap:20px;font:16px Arial;background:#eee}.sample{width:300px;flex:none;background:#2e9c87;padding:10px;box-sizing:content-box}.sample>h2{font:18px Arial;color:white;margin:0 0 20px}${css}</style></head><body>${samples}</body></html>`;
 await send('Runtime.evaluate',{expression:`document.open();document.write(${JSON.stringify(html)});document.close();`});
 const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true})).result.value;
 const metrics=await evaluate(`Object.fromEntries(['comfortable','compact','tight'].map(mode=>{const s=document.getElementById(mode),meta=s.querySelector('.pp-meta').getBoundingClientRect(),score=s.querySelector('.pp-score').getBoundingClientRect();return[mode,{height:s.querySelector('.petit-popular').getBoundingClientRect().height,inline:Math.abs(meta.top-score.top)<1}]}))`);
 if(!(metrics.compact.height<metrics.comfortable.height*.8&&metrics.tight.height<metrics.compact.height&&metrics.compact.inline))throw Error('Density did not compact layout: '+JSON.stringify(metrics));
 const doc=await send('DOM.getDocument');const link=await send('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'#compact .pp-title a'});
 await send('CSS.forcePseudoState',{nodeId:link.nodeId,forcedPseudoClasses:['hover']});
 const hover=await evaluate("getComputedStyle(document.querySelector('#compact .pp-title a')).color");
 await send('CSS.forcePseudoState',{nodeId:link.nodeId,forcedPseudoClasses:['focus-visible']});
 const focus=await evaluate("getComputedStyle(document.querySelector('#compact .pp-title a')).color");
 if(hover!=='rgb(227, 112, 143)'||focus!==hover)throw Error('Hover/focus mismatch: '+hover+' '+focus);
 const image=await send('Page.captureScreenshot',{format:'png'});writeFileSync(root+'/tests/density-browser.png',Buffer.from(image.data,'base64'));
 console.log('PASS: compact height, metadata alignment, hover and keyboard focus at 300px');console.log(JSON.stringify({metrics,hover,focus}));ws.close();
}finally{chrome.kill('SIGTERM')}
