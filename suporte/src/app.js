const express = require("express");


const app = express();

app.get("/", (req, res) => {
    res.json({"messager": "Hello, world"});
});

app.get("/contas", (req, res) => {
    res.json({"contas": "Suas contas"});
});

app.listen(3333);