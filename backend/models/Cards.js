const { Model, DataTypes } = require('sequelize');



class Cards extends Model {


  static init(sequelize){
    super.init({
      banco: DataTypes.STRING,
      conta: DataTypes.STRING ,
      agencia: DataTypes.STRING ,
      numero: DataTypes.STRING ,
      validade: DataTypes.STRING ,
      color: DataTypes.STRING,
      codigoSeguranca: DataTypes.STRING,
      limite: DataTypes.DECIMAL,
    },{
      sequelize, 
      tableName: 'cartoes'
    });
  }

}

module.exports = Cards;
