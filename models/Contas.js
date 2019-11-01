
const { Model, DataTypes } = require('sequelize');



class Contas extends Model {


  static init(sequelize){
    super.init({
      titulo: DataTypes.STRING,
      parcela: DataTypes.INTEGER,
      valor: DataTypes.DECIMAL,
      status: DataTypes.INTEGER,
      cartao: DataTypes.INTEGER,
    },{
      sequelize, 
      tableName: 'financasJr'
    });
  }

}

module.exports = Contas;
