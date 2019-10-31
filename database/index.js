const Sequelize = require('sequelize')
const dbConfig = require('../config/database');

// Import Models 


const connection = new Sequelize(dbConfig);


module.exports = connection;