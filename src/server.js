const express = require('express');
const mongoose = require('mongoose');
const cors = require('cors');
const path = require('path');


const routes = require('./routes');

const app = express();
mongoose.connect("mongodb://user:andreregedit@omnistack9-shard-00-00-4kshp.mongodb.net:27017,omnistack9-shard-00-01-4kshp.mongodb.net:27017,omnistack9-shard-00-02-4kshp.mongodb.net:27017/semana9?ssl=true&replicaSet=OmniStack9-shard-0&authSource=admin&retryWrites=true&w=majority", {
    useNewUrlParser: true,
    useUnifiedTopology: true,
});
app.use(cors());
app.use(express.json());
app.use('/files', express.static(path.resolve(__dirname, '..', 'uploads')));
app.use(routes);

var port = process.env.andre || 3000;
app.listen(port, function () {
    console.log('Umbler listening on port %s', port);
}); 