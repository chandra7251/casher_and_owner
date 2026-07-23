import { chromium } from 'playwright';
const b=await chromium.launch({headless:true}); const p=await b.newPage();
p.on('response',r=>{if(r.url().includes('/login')||r.url().includes('/owner')) console.log(r.status(),r.request().method(),r.url(),r.headers()['x-inertia-location']||'');});
await p.goto('http://127.0.0.1:8000/login');
await p.getByLabel('Email').fill('owner@kedaisenja.test'); await p.getByLabel('Password').fill('change-this-local-password');
await p.getByRole('button',{name:'Masuk'}).click(); await p.waitForTimeout(2000);
console.log('final',p.url(),(await p.locator('body').innerText()).slice(0,800)); await b.close();
