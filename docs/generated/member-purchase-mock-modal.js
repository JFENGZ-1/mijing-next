function(){var o=wx.showModal;wx.showModal=function(x){if(x&&x.title==='确认购买'){x.success&&x.success({confirm:true,cancel:false});return;}return o.call(wx,x)};return{ok:true}}
