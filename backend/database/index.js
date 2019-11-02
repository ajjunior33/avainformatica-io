const Sequelize = require('sequelize')
const dbConfig = require('../config/database');

// Import Models 

const Contas = require('../models/Contas');
const Users = require('../models/Users');
const Cards = require('../models/Cards');
const connection = new Sequelize(dbConfig);


Contas.init(connection);
Users.init(connection);
Cards.init(connection);

module.exports = connection;