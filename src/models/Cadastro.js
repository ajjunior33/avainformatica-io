const mongoose = require('mongoose');

const CadastrosSchema = new mongoose.Schema({
    nome: String,
    email: {
        type: String,
        required: true
    },
    telefone: String
});

module.exports = mongoose.model('Cadastros', CadastrosSchema);