const {chromium}=require(process.env.LMS_TEST_PLAYWRIGHT || 'playwright');
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const css=fs.readFileSync(root+'/assets/themes.css','utf8')+'\n'+fs.readFileSync(root+'/assets/compatibility.css','utf8');
const js=fs.readFileSync(root+'/assets/switcher.js','utf8')+'\n'+fs.readFileSync(root+'/assets/compatibility.js','utf8');
(async()=>{
 const browser=await chromium.launch({channel:process.env.LMS_TEST_BROWSER || 'chrome',headless:true});
 const context=await browser.newContext();
 await context.addInitScript(()=>localStorage.setItem('lms-theme-switcher:v1:user:42',JSON.stringify({theme:'lightgrey',density:'standard',sticky:true})));
 const page=await context.newPage();let requests=0;const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const html=`<!doctype html><html><head><style media="screen">${css}</style><script data-user-id="42">${js}</script></head><body class="lms-ui-main-document"><main id="lms-ui-module-view"><div id="lms-theme-settings"></div><div class="data-template-message-area"><table><tbody><tr id="rich" style="background-color:rgb(255,0,0)"><td>Authored table</td></tr></tbody></table></div><div data-lms-theme-preserve><table><tbody><tr id="preserve" style="background-color:rgb(255,0,0)"><td>Preserved table</td></tr></tbody></table></div><table><tbody><tr id="warning" style="background-color:rgb(255,0,0)"><td>Warning row</td></tr></tbody></table><div id="shared" style="overflow:hidden"><button id="business">Business action</button><table><thead><tr><th>Heading</th></tr></thead><tbody>${'<tr><td>Fixture</td></tr>'.repeat(100)}</tbody></table></div></main></body></html>`;
 await page.route('**/*',route=>{requests++;return route.fulfill({contentType:'text/html',body:html});});
 await page.goto('http://audit.invalid/');await page.waitForTimeout(100);
 const result=await page.evaluate(()=>({
   warning:getComputedStyle(document.getElementById('warning')).backgroundColor,
   authored:getComputedStyle(document.getElementById('rich')).backgroundColor,
   preserved:getComputedStyle(document.getElementById('preserve')).backgroundColor,
   sharedMarked:document.getElementById('shared').hasAttribute('data-lms-table-scroll'),
   sharedMaxHeight:getComputedStyle(document.getElementById('shared')).maxHeight,
   sharedOverflow:getComputedStyle(document.getElementById('shared')).overflowY,
 }));
 await page.selectOption('#lms-theme-theme','manual');
 const input=page.locator('#lms-color-hex-main');await input.fill('#12345');await input.press('6');
 result.hexAfterTyping=await input.inputValue();result.hexAriaInvalid=await input.getAttribute('aria-invalid');
 await input.fill('#abcdef');result.hexAfterPaste=await input.inputValue();
 await page.emulateMedia({media:'print'});
 result.printPreserved=await page.locator('#preserve').evaluate(e=>getComputedStyle(e).backgroundColor);
 await page.emulateMedia({media:'screen'});
 result.requests=requests;result.errors=errors;
 const assert=require('assert');
 for(const key of ['warning','authored','preserved','printPreserved'])assert.strictEqual(result[key],'rgb(255, 0, 0)',key);
 assert.strictEqual(result.sharedMarked,false);assert.strictEqual(result.sharedMaxHeight,'none');assert.strictEqual(result.sharedOverflow,'hidden');
 assert.strictEqual(result.hexAfterTyping,'#123456');assert.strictEqual(result.hexAfterPaste,'#abcdef');assert.strictEqual(result.requests,1);assert.deepStrictEqual(result.errors,[]);
 console.log('PASS warning colors, authored content, preserve marker, shared section layout, color editing and print.');console.log(JSON.stringify(result));
 await browser.close();
})().catch(e=>{console.error(e.message);process.exit(1)});
