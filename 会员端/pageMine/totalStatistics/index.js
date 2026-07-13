(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageMine/totalStatistics/index"],
  {
    "0ada": function (n, t, e) {
      (function (n, t) {
        var o = e("47a9");
        e("9785"), o(e("3240"));
        var a = o(e("b7ba"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), t(a.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    2523: function (n, t, e) {
      var o = e("efd5");
      e.n(o).a;
    },
    "2a90": function (n, t, e) {
      e.r(t);
      var o = e("3575"),
        a = e.n(o);
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return o[n];
            });
          })(i);
      t.default = a.a;
    },
    "2cfe": function (n, t, e) {
      e.d(t, "b", function () {
        return a;
      }),
        e.d(t, "c", function () {
          return i;
        }),
        e.d(t, "a", function () {
          return o;
        });
      var o = {
          uIcon: function () {
            return e
              .e("node-modules/uview-ui/components/u-icon/u-icon")
              .then(e.bind(null, "e4b0"));
          },
          uLoadmore: function () {
            return e
              .e("node-modules/uview-ui/components/u-loadmore/u-loadmore")
              .then(e.bind(null, "ffa0"));
          },
        },
        a = function () {
          this.$createElement;
          var n = (this._self._c, this.list.length),
            t = n > 0 ? null : this.imgsrc("/static/imgs/nodata.png");
          this.$mp.data = Object.assign({}, { $root: { g0: n, m0: t } });
        },
        i = [];
    },
    3575: function (n, t, e) {
      (function (n) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var o = e("888d"),
          a = {
            data: function () {
              return { loadStatus: "nomore", list: [], totalCount: 0 };
            },
            methods: {
              appointmentDetails: function (t) {
                n.navigateTo({
                  url: "/pageMine/appointmentStatistics/index?year="
                    .concat(t.year, "&month=")
                    .concat(t.month),
                });
              },
            },
            onLoad: function () {
              var t = this;
              (0, o.sumUserList)().then(function (e) {
                200 == e.code
                  ? ((t.list = e.yearlist), (t.totalCount = e.totalCount))
                  : n.showToast({ title: e.msg, icon: "none" });
              });
            },
          };
        t.default = a;
      }).call(this, e("df3c").default);
    },
    b7ba: function (n, t, e) {
      e.r(t);
      var o = e("2cfe"),
        a = e("2a90");
      for (var i in a)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return a[n];
            });
          })(i);
      e("2523");
      var u = e("828b"),
        c = Object(u.a)(
          a.default,
          o.b,
          o.c,
          !1,
          null,
          "1a501e98",
          null,
          !1,
          o.a,
          void 0,
        );
      t.default = c.exports;
    },
    efd5: function (n, t, e) {},
  },
  [["0ada", "common/runtime", "common/vendor"]],
]);
