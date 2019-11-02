const Cards = require("../models/Cards");


module.exports = {
    async index(req, res) {
        const cards = await Cards.findAll();

        //const addresses = await Address.findAll( {where : { user_id } } ); => Pode ser usado assim !
        return res.json(cards);

    },
    async store(req, res) {
        const {banco, conta, agencia, numero, validade, codigoSeguranca, limite} = req.body;
        const cards = await Cards.findOrCreate({
            where: {numero: numero },

            defaults:{
                banco: banco,
                conta: conta,
                agencia: agencia,
                numero: numero,
                validade: validade,
                codigoSeguranca: codigoSeguranca,
                limite: limite
            }
        });
        return res.json(cards);
    },
    async show(req, res) {
        const {id} = req.params;
        const cards = await Cards.findOne({
            where: {id: id },
        });
        return res.json(cards);
    }
}
