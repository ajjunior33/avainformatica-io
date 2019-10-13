const mongoose = require('mongoose');

const UserSchema = new mongoose.Schema({
    email: String,
});


// Mandando o Mango usar a estrutura do UserSchema
module.exports = mongoose.model('User', UserSchema);