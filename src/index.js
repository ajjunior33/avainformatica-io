const express = require("express");

const routes = require("./routes");

var app = express();
const port = process.env.ANDRE || 3333;

//... your code here ...
app.get("/", (req, res) => {
  return res.json({ messager: `Seu server está rodando na porta: ${port}` });
});

app.use(express.json());

app.use(routes);

app.listen(port, function () {
  console.log("Umbler listening on port %s", port);
});
