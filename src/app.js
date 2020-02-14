const express = require('express');
const cors = require("cors");
const mongoose = require("mongoose");

const Router = require("./router");
const app = express();

mongoose.connect("mongodb+srv://ajjunior33:andreregedit@cluster0-zrjud.mongodb.net/test?retryWrites=true&w=majority",{
    useNewUrlParser: true,
    useUnifiedTopology: true
});

app.use(cors());
app.use(express.json());
app.use(Router);

app.listen(process.env.andre || 3333, () => console.log("Escutando!"));