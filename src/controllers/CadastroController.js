const cadastros = require('../models/Cadastro');
module.exports = {
    async index ( req, res){
        const dados = await cadastros.find();
        return res.status(200).json(dados);
    },
    async store(req, res){
        const { nome, email, telefone } = req.body;
        if(await cadastros.findOne({email: email})){
            res.status(400).json({"messager": "Você já está cadastrado(a) em nossa base de dados."});
        }  else {
            const cadastrar = await cadastros.create({nome, email, telefone});
            if(cadastrar){
                return res.status(200).json({"messager": "Cadastrado com successo!"});
            }else{
                return res.status(400).json({"messager": "Não foi possivel cadastrar seu usuario, tente mais tarde."});
            }
        }
    },
    async delete(req, res){
        const { id } = req.params;
        if(await cadastros.deleteOne({"_id": id})){
            return res.json({"messager": "Deletado com sucesso!"});
        }else {
            return res.son({"messager": "Não foi possivel deletar o cadastro."});
        }
    }
}