const Users = require("../models/Users");
const md5 = require("md5");
const sha1 = require("sha1");

module.exports = {
    async index(req, res) {
        const { email, password } = req.body;

        let status = false;

        const users = await Users.findOne({
            where: {
                email: email,
                senha: md5(sha1(password))
            }
        });
        if (users) {
            status = true;
        }

        //const addresses = await Address.findAll( {where : { user_id } } ); => Pode ser usado assim !
        return res.json(status);

    }
}
