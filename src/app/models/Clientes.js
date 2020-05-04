const connection = require("../database/connection");

const promise = connection.promise();
module.exports = {
  lista(array) {
    const values = [];
    array.forEach((element, index) => {
      values.push(element);
    });

    return values;
  },
  async verify(document) {
    const sql = "SELECT * FROM customer WHERE document = ?";
    const [rows, fields] = await promise.query(sql, [document]).catch((err) => {
      return res.status(400).json({
        status_code: 400,
        status: false,
        messager: "Customer search error.",
      });
    });
    if (rows.length === 0) {
      return true;
    } else {
      return false;
    }
  },
};
