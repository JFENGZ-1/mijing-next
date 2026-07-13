require("../../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/components/kindReminder/personalCourse"],
    {
      "42a6": function (n, e, o) {
        "use strict";
        var t = o("72bb");
        o.n(t).a;
      },
      "64ce": function (n, e, o) {
        "use strict";
        o.r(e);
        var t = o("cbda"),
          r = o("c2ed");
        for (var a in r)
          ["default"].indexOf(a) < 0 &&
            (function (n) {
              o.d(e, n, function () {
                return r[n];
              });
            })(a);
        o("42a6");
        var u = o("828b"),
          c = Object(u.a)(
            r.default,
            t.b,
            t.c,
            !1,
            null,
            null,
            null,
            !1,
            t.a,
            void 0,
          );
        e.default = c.exports;
      },
      "72bb": function (n, e, o) {},
      "8a4e": function (n, e, o) {
        "use strict";
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0),
          (e.default = {
            props: { kindReminder: {} },
            data: function () {
              return { loading: !0, info: {} };
            },
          });
      },
      c2ed: function (n, e, o) {
        "use strict";
        o.r(e);
        var t = o("8a4e"),
          r = o.n(t);
        for (var a in t)
          ["default"].indexOf(a) < 0 &&
            (function (n) {
              o.d(e, n, function () {
                return t[n];
              });
            })(a);
        e.default = r.a;
      },
      cbda: function (n, e, o) {
        "use strict";
        o.d(e, "b", function () {
          return r;
        }),
          o.d(e, "c", function () {
            return a;
          }),
          o.d(e, "a", function () {
            return t;
          });
        var t = {
            uParse: function () {
              return Promise.all([
                o.e("common/vendor"),
                o.e("uview-ui/components/u-parse/u-parse"),
              ]).then(o.bind(null, "eb32"));
            },
          },
          r = function () {
            this.$createElement;
            this._self._c;
          },
          a = [];
      },
    },
  ]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageConfig/components/kindReminder/personalCourse-create-component",
    {
      "pageConfig/components/kindReminder/personalCourse-create-component":
        function (n, e, o) {
          o("df3c").createComponent(o("64ce"));
        },
    },
    [["pageConfig/components/kindReminder/personalCourse-create-component"]],
  ]);
