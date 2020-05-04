const connection = require("../database/connection");
const Clientes = require("../models/Clientes");

const promise = connection.promise();
module.exports = {
  async index(req, res) {
    const [rows, fields] = await promise
      .query("SELECT * FROM customer")
      .catch((err) => {
        return res.status(400).json({
          status_code: 200,
          messager: "Error loading client list",
        });
      });
    return res
      .status(200)
      .json({ status_code: 200, messager: "Customer list", data: rows });
  },
  async store(req, res) {
    const { name, email, phone, document, document_type } = req.body;
    let { telephone } = req.body;
    if (telephone === "") {
      telephone = phone;
    }
    let datacreate = [
      name.toUpperCase(),
      email.toLowerCase(),
      telephone,
      phone,
      document,
      document_type,
    ];

    if ((await Clientes.verify(document)) == false) {
      return res.status(400).json({
        status_code: 400,
        status: false,
        messager: "Customer already in the database.",
      });
    }
    const values = Clientes.lista(datacreate);
    let stmt = `INSERT INTO customer(name, email, telephone, phone, document, document_type)
            VALUES(?,?,?,?,?,?)`;
    const [err, result] = await promise.query(stmt, values).catch((err) => {
      return res.status(400).json({
        status_code: 400,
        err,
        messager: "Error loading client list",
      });
    });
    return res.status(200).json({
      status_code: 200,
      status: true,
      messager: "Customer successfully created.",
    });
  },
  async update(req, res) {
    let { document, email, telephone, phone } = req.body;
    let values = [];

    if ((await Clientes.verify(document)) == true) {
      return res.status(400).json({
        status_code: 400,
        status: false,
        messager: "Client not found.",
      });
    }
    if (phone === "") {
    } else {
      values.push(phone);
      if (telephone === "") {
        telephone = phone;
        values.push(telephone);
      }
    }
    values.push(email);

    val = Clientes.lista(datacreate);
    console.log(val);
  },
};
