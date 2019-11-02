const Sequelize = require('sequelize')
const dbConfig = require('../config/database');

// Import Models 

const Contas = require('../models/Contas');
const Users = require('../models/Users');
const connection = new Sequelize(dbConfig);


Contas.init(connection);
Users.init(connection);

module.exports = connection;