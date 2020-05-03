var express = require("express");
var app = express();

var port = process.env.ANDRE || 3000;

//... your code here ...
app.get("/", (res, res) => {
  return res.json({ messager: `Seu server está rodando na porta: ${port}` });
});

app.listen(port, function () {
  console.log("Umbler listening on port %s", port);
});
