const Contas = require("../models/Contas.js");

module.exports = {
    async index(req, res) {
      res.header("Access-Control-Allow-Origin", "YOUR-DOMAIN.TLD"); // update to match the domain you will make the request from
      res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
      
      const users = await Contas.findAll({where: { status: 0 }});

    // const addresses = await Address.findAll( {where : { user_id } } ); => Pode ser usado assim !
    return res.json(users);

  },
  async show(req, res){
    let {card_id} = req.body;
    const user = await Contas.findAll({ where: { cartao: card_id }} );

    return res.json(user);
  },

  async pagar( req, res ){
    let status = false; 
    const { id } = req.params;
    const contaPaga = await Contas.update({status: 1}, {where: {id: id } });
    
    if(contaPaga){
      status = true;
    }

    return res.json(status);
  },

  async extorno( req, res ) {
     let status = false;
     const { id } = req.params;
     const contaExtonar = await Contas.update({status: 0}, {where: { id: id }});

     if(contaExtonar){
       status = true;
     }

     return res.json(status); 
  },
  
  async delete( req, res ) {
    let status = false;
    const { id } = req.params;
    const deleteConta = await Contas.destroy({where: { id: id }});
    if(deleteConta){
      status = true;
    }
    return res.json(status);  
  },

  async store( req, res ) {
    let state = false;

    const {titulo, parcela, valor, status, cartao} = req.body;
    const adicionar = await Contas.create({titulo, parcela, valor, status, cartao});
    if(adicionar){
      state = true;
    }

    return res.json(state);
    
  }

}
