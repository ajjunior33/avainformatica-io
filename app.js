const express = require('express');
const app = express();


app.get('/', (req, res) => {
    res.send('Hello, World!');
})

var port = process.env.andre || 3000;
app.listen(port, function () {
    console.log('Umbler listening on port %s', port);
});
