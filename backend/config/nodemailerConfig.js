const nodemailer = require('nodemailer');

const transporter = nodemailer.createTransport({
    host: 'smtp.umbler.com',
    port: 587,
    sercure: false, // true => para 465, false=> outras portas
    auth:{
        user: "andrejr@suporteava.com.br",
        pass: "andreregedit"
    },
    tls: { rejectUnauthorized: false }

});

module.exports = transporter;
