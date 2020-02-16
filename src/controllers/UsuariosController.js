const usuarios = require('../models/Usuarios');
const sha1 = require('sha-1');
module.exports = {
    async auth ( req, res){
        const { email, senha } = req.body;
        if(await usuarios.findOne({email})){
            const newPassword = sha1(senha);
            const auth = await usuarios.findOne({email, "senha": newPassword});
            if(auth){
                return res.json({"messager": "Acessado!", "auth": auth});
            
            }else{
                return res.json({"messager": "Email ou Senha incorreta."});
            }
        }else{
            return res.json({"messager": "Não encontramos esse email."});
        }
    },
    async store(req, res){
        const { nome, email, senha } = req.body;
        const newPassword = sha1(senha);
        if(await cadastros.findOne({email: email})){
            return res.json({"messager": "Você já está cadastrado(a) em nossa base de dados."});
        }  else {
            const cadastrar = await usuarios.create({nome, email, newPassword});
            if(cadastrar){
                return res.json({"messager": "Cadastrado com successo!"});
            }else{
                return res.json({"messager": "Erro ao cadastrar o Client."});
            }
        }
    },
}