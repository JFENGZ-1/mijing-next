(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageHome/informDetails/index"],
  {
    "28d5": function (n, t, e) {
      e.r(t);
      var a = e("f463"),
        c = e("33ca");
      for (var f in c)
        ["default"].indexOf(f) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return c[n];
            });
          })(f);
      e("c3f3");
      var o = e("828b"),
        u = Object(o.a)(
          c.default,
          a.b,
          a.c,
          !1,
          null,
          "0cf2c342",
          null,
          !1,
          a.a,
          void 0,
        );
      t.default = u.exports;
    },
    "33ca": function (n, t, e) {
      e.r(t);
      var a = e("fe66"),
        c = e.n(a);
      for (var f in a)
        ["default"].indexOf(f) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return a[n];
            });
          })(f);
      t.default = c.a;
    },
    "74be": function (n, t, e) {
      (function (n, t) {
        var a = e("47a9");
        e("9785"), a(e("3240"));
        var c = a(e("28d5"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), t(c.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "9a24": function (n, t, e) {},
    c3f3: function (n, t, e) {
      var a = e("9a24");
      e.n(a).a;
    },
    f463: function (n, t, e) {
      e.d(t, "b", function () {
        return a;
      }),
        e.d(t, "c", function () {
          return c;
        }),
        e.d(t, "a", function () {});
      var a = function () {
          this.$createElement;
          this._self._c;
        },
        c = [];
    },
    fe66: function (n, t, e) {
      Object.defineProperty(t, "__esModule", { value: !0 }),
        (t.default = void 0);
      var a = e("f46d"),
        c = {
          data: function () {
            return { record: null };
          },
          onLoad: function (n) {
            var t = this,
              e = n.noticeId;
            (0, a.getNoticeList)().then(function (n) {
              var a = n.datalist.find(function (n) {
                return n.noticeId == e;
              });
              t.record = a;
            });
          },
        };
      t.default = c;
    },
  },
  [["74be", "common/runtime", "common/vendor"]],
]);
