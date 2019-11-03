'use strict';

module.exports = {
  up: (queryInterface, Sequelize) => {
    return queryInterface.createTable('cards', {
      id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true,
        allowNull: false,
      },
      banco: {
        type: Sequelize.STRING,
        allowNull: false,
      },
      nome:{
        type: Sequelize.STRING,
        allowNull: false,
      },
      conta: {
        type: Sequelize.STRING,
        allowNull: false,
      },
      agencia: {
        type: Sequelize.STRING,
        allowNull: false,
      },      
      numero: {
        type: Sequelize.STRING,
        allowNull: false,
      },
      validade: {
        type: Sequelize.TINYINT,
        allowNull: false,
      },
      color: {
        type: Sequelize.STRING,
        allowNull: false,
      },
      codigo_seguranca: {
        type: Sequelize.INTEGER,
        allowNull: false,
      },
      limite: {
        type: Sequelize.DECIMAL,
        allowNull: false,
      },
      created_at: {
        type: Sequelize.DATE,
        allowNull: false,
      },
      updated_at: {
        type: Sequelize.DATE,
        allowNull: false,
      }
    });

  },

  down: (queryInterface, Sequelize) => {
    return queryInterface.dropTable('cards');
  }
};
