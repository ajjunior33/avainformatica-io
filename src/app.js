require("dotenv").config();
const express = require("express");
const cors = require("cors");

const routes = require("./routes");
const app = express();

app.use(express.json());
app.use(cors());
app.get("/", (req, res) => {
  return res.status(200).json({ messager: "Hello, world" });
});

app.use(routes);
app.listen(process.env.andre || 3333, () => console.log("Escutando!"));
