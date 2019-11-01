const Sequelize = require('sequelize')
const dbConfig = require('../config/database');

// Import Models 

const Contas = require('../models/Contas');

const connection = new Sequelize(dbConfig);


Contas.init(connection);

module.exports = connection;