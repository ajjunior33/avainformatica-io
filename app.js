const express = require('express');
const transporter = require('./config/nodemailerConfig');
const app = express();


app.use(express.json());

app.get('/', (req, res) => {
    const {email, mensagem} = req.body;
    res.json({email: email, mensagem: mensagem});
});

app.get('contas', (req, res) =>{

    res.json({ok: "Ok"});
});


app.get("/send", (req, res) => {
  const {title, mensagem, subTitle, email} = req.body;
  const mailOptions = {
      from: 'andrejr@suporteava.com.br',
      to: email,
      subject: title,
      //text: 'Olá, mundo!'
      html: `<h1>${subTitle}. </h1><br> <p> ${mensagem} </p>`,
  }
  console.log(mailOptions['html'])

  transporter.sendMail(mailOptions, (error, info) => {
      if(error){
          return res.json(error);
      }else{
          return res.json("E-mail enviado: " + info.response);
      }
  });

});


const port = process.env.andre || 3000;
app.listen(port, function () {
    console.log('Umbler listening on port %s', port);
});
