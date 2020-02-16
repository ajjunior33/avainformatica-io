const mongoose = require('mongoose');

const UsuariosSchema = new mongoose.Schema({
    nome: String,
    email: {
        type: String,
        required: true
    },
    senha: {
        type: String,
        required: true
    }
});

module.exports = mongoose.model('Usuarios', UsuariosSchema);