// Lightweight isolated browser contract checks. No network, database or services.
const {strict: assert} = require('node:assert');
let checks = 0;
const equal = (...args) => { checks++; assert.equal(...args); };
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const vm = require('node:vm');
const {webcrypto} = require('node:crypto');
const root = resolve(__dirname, '../..');
const source = readFileSync(resolve(root, 'Admin Panel/public/assets/marketing/tracking.js'), 'utf8');
equal(source, readFileSync(resolve(root, 'User app and web/web/marketing_tracking.js'), 'utf8'), 'Web bridge copies must match');
function node(tag) { return {tag, children: [], style: {}, setAttribute(){}, append(...v){this.children.push(...v)}, appendChild(v){this.children.push(v)}, remove(){this.removed = true}}; }
const body = node('body'), head = node('head'), listeners = {}, storage = new Map(), calls = [];
let serverChoice, backendEvents = [], receiptNumber = 0, fakeNow = Date.now();
class Clock extends Date { static now(){return fakeNow} }
const window = {dataLayer: [], addEventListener(name, fn){listeners[name] = fn}};
const config = {enabled: true, policy_version: '2026-11-05.1', providers: {gtm: {enabled: true, ids: {identifier: 'GTM-ABCD123'}}}};
const context = {window, document: {currentScript: {dataset: {}}, readyState: 'complete', head, body, referrer: 'https://example.com/private?phone=secret',
  createElement: node, createTextNode: text => ({text}), addEventListener(name, fn){listeners[name] = fn}},
  location: {origin:'https://app.example.com', pathname:'/service/opaque-id', search:'?utm_source=test&fbclid=click123', hash:''},
  history:{replaceState(){}}, localStorage:{getItem:key=>storage.get(key), setItem:(k,v)=>storage.set(k,v)},
  URL, URLSearchParams, crypto: webcrypto, Date: Clock, Set, Object, console, setTimeout: () => 0, clearTimeout: () => {},
  fetch: async (url, options) => {
    calls.push({url, options}); let data;
    if (url.includes('config?')) data = config;
    else if (url.endsWith('/consent') && options.method === 'POST') {
      const p = JSON.parse(options.body);
      serverChoice = {token: (++receiptNumber).toString(16).padStart(64, 'a'), analytics:p.analytics, advertising:p.advertising, policy_version:config.policy_version,
        chosen_at:new Date().toISOString(), expires_at:new Date(Date.now()+86400000).toISOString()}; data = serverChoice;
    } else if (url.endsWith('/consent')) data = {...serverChoice, valid:!!serverChoice};
    else if (url.endsWith('verified-events')) data = {events:backendEvents};
    else data = {};
    return {ok:true, status:200, json:async()=>data};
  }};
vm.runInNewContext(source, context);
const api = window.nirabMarketing;
const panel = () => body.children.filter(n=>n.tag==='section'&&!n.removed).at(-1);
const inputs = () => panel().children.filter(n=>n.tag==='label').map(n=>n.children[0]);
const save = () => panel().children.filter(n=>n.tag==='button')[0].onclick();
(async()=>{
  await api.init();
  equal(head.children.length,0,'No GTM request before consent');
  equal(api.push({event:'add_to_cart',event_id:webcrypto.randomUUID()}),false);
  inputs()[0].checked=true; inputs()[1].checked=false; await save();
  equal(head.children.length,1,'One validated GTM loader');
  const grant = calls.filter(c=>c.options.method==='POST'&&c.url.endsWith('/consent')).at(-1);
  equal(JSON.parse(grant.options.body).attribution.fbclid,undefined,'Click ID excluded without advertising consent');
  const id=webcrypto.randomUUID();
  equal(api.push({event:'add_to_cart',event_id:id,currency:'SAR',value:100,phone:'secret',items:[{item_id:'catalog-id',quantity:1,price:100,address:'secret'}]}),true);
  equal(api.push({event:'add_to_cart',event_id:id}),false,'Stable event dedup');
  const cart=window.dataLayer.find(p=>p.event==='add_to_cart');
  equal(cart.phone,undefined); equal(cart.ecommerce.items[0].address,undefined);
  equal(cart.nirab_advertising,false); equal(cart.ecommerce.currency,'SAR');
  equal(api.push({event:'purchase',event_id:webcrypto.randomUUID(),value:100,currency:'SAR'}),false,'Client cannot mint purchase');
  equal(api.push({event:'purchase',event_id:webcrypto.randomUUID(),value:100,currency:'SAR'},true),false,'No public verified-source override');
  config.providers.ga4 = {enabled:true,ids:{identifier:'G-ABCDE123'}};
  config.providers.meta = {enabled:true,ids:{identifier:'123456789'}};
  api.sendTag('meta',id); equal(head.children.length,1,'Advertising tag blocked for analytics-only consent');
  api.sendTag('ga4',id); const afterGa4 = head.children.length;
  api.sendTag('ga4',id); equal(head.children.length,afterGa4,'Provider tag cannot replay an event');
  equal(window.dataLayer.some(p=>p[0]==='event'&&p[1]==='add_to_cart'&&p[2].send_to==='G-ABCDE123'),true,'GTM adapter queues one standard GA4 event');
  const verifiedId = webcrypto.randomUUID();
  backendEvents = [{event:'purchase',event_id:verifiedId,transaction_id:'booking:'+webcrypto.randomUUID(),value:115,currency:'SAR'}];
  fakeNow += 6000; await api.verified();
  equal(window.dataLayer.some(p=>p.event==='purchase'&&p.event_id===verifiedId),true,'Purchase accepted only from verified backend feed');
  equal(calls.some(c=>c.url.endsWith('/ack')&&JSON.parse(c.options.body).event_ids.includes(verifiedId)),true,'Backend ACK means offered, not vendor delivery');

  const page=window.dataLayer.find(p=>p.event==='page_view');
  equal(page.page_location,'https://app.example.com/service'); equal(page.page_referrer,'https://example.com');
  const pageCount = window.dataLayer.filter(p=>p.event==='page_view').length, navigation = webcrypto.randomUUID();
  api.page('/service/another-product', navigation);
  equal(window.dataLayer.filter(p=>p.event==='page_view').length,pageCount+1,'Distinct navigation to same coarse route is measured');
  api.page('/service/another-product', navigation);
  equal(window.dataLayer.filter(p=>p.event==='page_view').length,pageCount+1,'Same navigation ID does not duplicate pageview');
  api.show(); inputs()[0].checked=false; inputs()[1].checked=false; await save();
  equal(api.push({event:'view_item',event_id:webcrypto.randomUUID()}),false,'Withdrawal immediately blocks events');
  equal(head.children.length,afterGa4,'Withdrawal never reloads GTM or providers');
  equal(window.dataLayer.filter(p=>p.event==='nirab_consent_update').at(-1).nirab_analytics,false);
  console.log(`PASS: ${checks} Web consent, privacy, schema, dedup and provider-gating assertions; no network used.`);
})().catch(e=>{console.error(e);process.exitCode=1});
