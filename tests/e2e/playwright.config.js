const {defineConfig}=require('@playwright/test');
module.exports=defineConfig({testDir:'.',timeout:30000,retries:0,use:{baseURL:process.env.BASE_URL||'http://127.0.0.1:18080',trace:'retain-on-failure'},reporter:[['list'],['html',{outputFolder:'report',open:'never'}]]});
