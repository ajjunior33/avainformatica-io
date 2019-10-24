const express = require('express');
const UserController = require('./controllers/UserController');
const UserController = require('./controllers/AddressController');

const routes = express.Router();

routes.get('/', (req, res) =>{
  return res.send("Olá, mundo !");
})
routes.post('/users', UserController.store);
routes.get('/list', UserController.index);
routes.post('/user/:user_id/addressess', AddressController.store);
module.exports = routes;
