const Users = require("../models/Users");

module.exports = {
    async index(req, res) {
        const { email, password } = req.body;
        const users = await Users.findOne({ where: { emaill: email, senha: md5(password) } });

        // const addresses = await Address.findAll( {where : { user_id } } ); => Pode ser usado assim !
        return res.json(users);

    }
}
