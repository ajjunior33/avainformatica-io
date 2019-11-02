
const { Model, DataTypes } = require('sequelize');



class Users extends Model {


  static init(sequelize){
    super.init({
      email: DataTypes.STRING,
      usuario: DataTypes.STRING,
      permissao: DataTypes.STRING,
      nome: DataTypes.INTEGER,
        },{
      sequelize, 
      tableName: 'user'
    });
  }

}

module.exports = Users;
