const express = require('express');

const app = express();

app.get("/", (req, res) => {
    return res.json({"saudacao": "Olá, mundo!"});
});


app.listen(3000);