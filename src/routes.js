const express = require('express');
const UserController = require('./controllers/UserController');
const AddressController = require('./controllers/AddressController');
const TechController = require('./controllers/TechController');
const ReportController = require('./controllers/ReportController');

const routes = express.Router();

routes.get('/', (req, res) =>{
  return res.send("Olá, mundo !");
})
routes.post('/users', UserController.store);
routes.get('/list', UserController.index);

routes.get('/users/:user_id/addressess', AddressController.index);
routes.post('/users/:user_id/addressess', AddressController.store);

routes.get('/users/:user_id/techs', TechController.index);
routes.post('/users/:user_id/techs', TechController.store);
routes.delete('/users/:user_id/techs', TechController.delete);

routes.get('/report', ReportController.show);

module.exports = routes;
