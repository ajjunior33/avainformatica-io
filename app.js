var express = require('express');
var app = express();

app.get("/", function(req, res){
	res.send("<h1> Olá, mundo!");
});


var port = process.env.PORT || 3000;
app.listen(port, function(){
	console.log("Umbler listening on port %s", port);
});
