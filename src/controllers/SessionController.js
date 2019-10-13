// index, show, store, update, destroy
/*
 * index -> Retorna Listagem
 * show -> unica sessao
 * store -> criar uma sessao
 * update -> alterar uma sessao
 * destroy -> deletar/remover uma sessao
 */

const User = require("../models/Users");
module.exports = {
    index(req, res) {
        return res.send("Olá, mundo!");

    },
    async store(req, res) {
        const { email } = req.body;
        let user = await User.findOne({ email });
        if (!user) {
            user = await User.create({ email });
        }
        return res.json(user);
    }
};