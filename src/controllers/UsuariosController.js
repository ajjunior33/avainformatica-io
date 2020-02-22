const usuarios = require('../models/Usuarios');
const sha1 = require('sha-1');
module.exports = {
    async auth ( req, res){
        const { email, senha } = req.body;
        if(await usuarios.findOne({email})){
            const newPassword = sha1(senha);
            const auth = await usuarios.findOne({email, "senha": newPassword});
            if(auth){
                return res.json({"status": true, "messager": "Acessado!", "auth": auth});
            
            }else{
                return res.json({"status": false, "messager": "Email ou Senha incorreta."});
            }
        }else{
            return res.json({"status": false,"messager": "Não encontramos esse email."});
        }
    },
    async store(req, res){
        const { nome, email, senha } = req.body;
        const newPassword = sha1(senha);
        const data = new Date();
        if(await usuarios.findOne({email: email})){
            return res.json({"messager": "Você já está cadastrado(a) em nossa base de dados."});
        }  else {
            const token = sha1(email + data.getTime());
            const cadastrar = await usuarios.create({nome, email, "senha": newPassword, token});
            if(cadastrar){
                return res.json({"messager": "Cadastrado com successo!"});
            }else{
                return res.json({"messager": "Erro ao cadastrar o Client."});
            }
        }
    },
    async checkAuth(req, res){
        const {token} = req.params;

        const check = await usuarios.findOne({token: token});
        if(check){
            return res.json(true);
        }else{
            return res.json(false);
        }
    }
}