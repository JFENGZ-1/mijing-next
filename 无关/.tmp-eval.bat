@echo off
set PROJ=D:/Users/Zhong/Desktop/????????/mijing-next/apps/member-miniapp/dist/build/mp-weixin
wechatide -c Cursor -t automation_evaluate --project %PROJ% --fn-source "function(){uni.showModal=function(o){if(o&&o.success)o.success({confirm:true,cancel:false});if(o&&o.complete)o.complete({confirm:true,cancel:false});return Promise.resolve({confirm:true});};return 'patched';}"
