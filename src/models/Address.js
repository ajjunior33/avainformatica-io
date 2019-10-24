const { Model, DataTypes } = require('sequelize');



class Address extends Model {


  static init(sequelize){
    super.init({
      zipcode: DataTypes.STRING,
      street: DataTypes.STRING,
      street: DataTypes.INTEGER,
    },{
      sequelize
    });
  }

  static associate(models){
    this.belongsTo(mode.User, { foreignKey: 'user_id', as: 'user'});
  }


}

module.exports = User;
