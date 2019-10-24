const { Op } = require('sequelize');
const User = require('../models/User');
module.exports = {
    async show(req, res) {

        const users = await User.findAll({
            attributes: ['name', 'email'],
            where: {
                email: {
                    [Op.like]: '%@gmail.com'
                }
            },
            include: [
                { association: 'addresses', where: { street: 'Valadares' } }, //Endereços
                {
                    association: 'techs',
                    required: false, 
                    where: {
                        name: {
                            [Op.like]: 'React%'
                        }
                    }
                }, // Tecnologias
            ]
        })
        return res.json(users);
    }
}