const express = require('express');
const routes = require("./routes");
require('./database');

const app = express();


app.use(express.json());
app.use(routes);
const port = process.env.andre || 3000;
app.listen(port, function () {
    console.log('Umbler listening on port %s', port);
});
