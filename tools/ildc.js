var path = require('path');

var FIBJS_PATH = path.join(__dirname, `../fibjs/`)

var parser = require(path.join(FIBJS_PATH, 'tools/util/parser'));
var gen_code = require(path.join(FIBJS_PATH, 'tools/util/gen_code'));

var idlLang = process.env.FIBJS_IDL_LANG || 'zh-cn'
var fibjs_idlFolder = path.join(FIBJS_PATH, `idl/${idlLang}`);
var idlFolder = path.join(__dirname, `../idl/${idlLang}`);

var baseCodeFolder = path.join(__dirname, "../include/ifs/");

var defs = parser(fibjs_idlFolder);
defs = parser(idlFolder, defs);

gen_code(defs, baseCodeFolder);

module.exports = defs;