const express = require("express");

const app = express();

app.get("/", (req, res) => {
    console.log("hello, world");
    res.json({mesager: "hello, world"});
});

app.listen(3333, () => {
    console.log('rodando na 3333 ')
})