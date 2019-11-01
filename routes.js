const express = require('express');

const ContasController = require("./controllers/ContasController");

const routes = express.Router();
const transporter = require('./config/nodemailerConfig');

routes.use(function(req, res, next) {
    res.header("Access-Control-Allow-Origin", "*");
    res.header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");
    next();
  });

routes.get('/', (req, res) => {
    const { email, mensagem } = req.body;
    res.json({ email: email, mensagem: mensagem });
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


routes.get("/listContas", ContasController.index);
routes.get("/listConta", ContasController.show);

routes.post("/novaConta", ContasController.store);
routes.post("/pagar/:id", ContasController.pagar);
routes.post("/extornar/:id", ContasController.extorno);
routes.delete("/delete/:id", ContasController.delete);


module.exports = routes;