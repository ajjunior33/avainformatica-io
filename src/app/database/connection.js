const mysql = require("mysql2");
const connection = mysql.createPool({
  host: "mysql669.umbler.com",
  port: 41890,
  user: "whitecode",
  password: "andreregedit",
  database: "whitecode",
});

module.exports = connection;
