function(){var p=getCurrentPages().pop(),v=p.$vm,s=v&&v.setupState;return{hasVm:!!v,hasSetup:!!s,products:s&&s.products?s.products.value.length:0,dataKeys:p.data?Object.keys(p.data):[]}}
