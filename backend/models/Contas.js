
const { Model, DataTypes } = require('sequelize');
class Contas extends Model {

  static init(sequelize){
    super.init({
      titulo: DataTypes.STRING,
      parcela: DataTypes.INTEGER,
      valor: DataTypes.DECIMAL,
      status: DataTypes.INTEGER,
      card_id: DataTypes.INTEGER,
    },{
      sequelize, 
      tableName: 'finances'
    });
  }

  static associate(models) {
    this.belongsTo(models.Cards, { foreignKey: 'card_id', as: 'owner' });
  }

}

module.exports = Contas;
