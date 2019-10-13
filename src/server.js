var express = require('express');
var app = express();

app.get("/", (req, res) => {
    return res.send("<h1>Amor</h1>");
}); 
app.get("/login", (req, res) => {
    return res.json({"login" : "logar-se"});
}); 
                                
var port = process.env.andre || 3000;
app.listen(port, function () {
    console.log('Umbler listening on port %s', port);
});