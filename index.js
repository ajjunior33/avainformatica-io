var express = require('express');
var app = express();

app.get("/", (req, res) => {
    return res.send("<h1> Olá, mundo! </h1>");
});
                                
var port = process.env.andre || 3000;
app.listen(port, function () {
    console.log('Umbler listening on port %s', port);
});