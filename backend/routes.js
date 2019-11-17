const express = require('express');

const ContasController = require("./controllers/ContasController");
const UsersController = require("./controllers/UsersController");
const CartoesController = require("./controllers/CartaoController");

const routes = express.Router();
const transporter = require('./config/nodemailerConfig');



routes.use(function (req, res, next) {
    res.header("Access-Control-Allow-Origin", "*");
    res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
    next();
});



routes.get('/', (req, res) => {
    const { email, mensagem } = req.body;
    res.json({ email: email, mensagem: mensagem });
});




routes.post("/teste", (req, res) => {
    const email = req.body.email;
    console.log(email);
    return res.json({ mail: email, hello: "World" });
});

routes.get('/contas', (req, res) => {

    res.json({ ok: "Ok" });
});



routes.get("/send", (req, res) => {
    const { title, mensagem, subTitle, email } = req.body;
    const mailOptions = {
        from: 'andrejr@suporteava.com.br',
        to: email,
        subject: title,
        //text: 'Olá, mundo!'
        html: `<h1>${subTitle}. </h1><br> <p> ${mensagem} </p>`,
    }
    transporter.sendMail(mailOptions, (error, info) => {
        if (error) {
            return res.json(error);
        } else {
            return res.json("E-mail enviado: " + info.response);
        }
    });

});

routes.get("/subsend", (req, res) => {
    const { title, mensagem, email } = req.body;
    const mailOptions = {
        from: 'andrejr@suporteava.com.br',
        to: email,
        subject: title,
        //text: 'Olá, mundo!'
        html: `${mensagem}. <br><br><br><br> <i>Create by A.V.A Informática </i>`,
    }
    transporter.sendMail(mailOptions, (error, info) => {
        if (error) {
            return res.json(error);
        } else {
            return res.json("E-mail enviado: " + info.response);
        }
    });

});

routes.get("/sendAnexo", (req, res) => {
    const { title, mensagem, email } = req.body;
    const mailOptions = {
        from: 'andrejr@suporteava.com.br',
        to: email,
        subject: title,
        //text: 'Olá, mundo!'
        html: `${mensagem}. <br><br><br><br> <i>Create by A.V.A Informática </i>`,
        attachments: [{ // Basta incluir esta chave e listar os anexos
            filename: 'euportodomundo.pdf', // O nome que aparecerá nos anexos
            path: 'http://euportodomundo.com.br/dir/sample.pdf' // O arquivo será lido neste local ao ser enviado
        }]
    }
    transporter.sendMail(mailOptions, (error, info) => {
        if (error) {
            return res.json(error);
        } else {
            return res.json("E-mail enviado: " + info.response);
        }
    });

});


routes.get("/listContas", ContasController.index);
routes.get("/listConta", ContasController.show);


/*LOGIN*/
routes.post("/login/", UsersController.index);

/* CONTAS */
routes.post("/novaConta", ContasController.store);
routes.post("/pagar/:id", ContasController.pagar);
routes.post("/extornar/:id", ContasController.extorno);
routes.delete("/delete/:id", ContasController.delete);


/*CARTOES*/

routes.get("/listCards", CartoesController.index);
routes.post("/addCards", CartoesController.store);
routes.get("/listCard/:id", CartoesController.show);
routes.get("/listCardT", ContasController.showlist);
module.exports = routes;