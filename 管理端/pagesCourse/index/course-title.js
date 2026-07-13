(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesCourse/index/course-title"],
  {
    "1c25": function (t, n, e) {
      "use strict";
      var o = e("fcff");
      e.n(o).a;
    },
    "33e4": function (t, n, e) {
      "use strict";
      (function (t, n) {
        var o = e("47a9");
        e("86d2"), o(e("3240"));
        var i = o(e("c6a8"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(i.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "3d78": function (t, n, e) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var o = e("abae"),
          i = {
            data: function () {
              return {
                background: "#FFFFFF",
                headtitle: "修改课程标题",
                title: "",
                placeholder: "提示内容",
                num: "",
              };
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var n = t.getMenuButtonBoundingClientRect();
                return (
                  n.height +
                  2 * (n.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            components: {
              navigation: function () {
                e.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(e("af9e"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
              hint: function () {
                e.e("pagesCourse/components/top-hint/index")
                  .then(
                    function () {
                      return resolve(e("a8d3"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
            },
            onLoad: function () {
              this.getPlanTitle();
            },
            methods: {
              getPlanTitle: function () {
                var t = this;
                (0, o.getPlanTitle)().then(function (n) {
                  t.title = n.data;
                  var e = (0, o.countLetters)(t.title),
                    i = e.english,
                    u = e.chinese;
                  t.num = Math.ceil((2 * u + i) / 2);
                });
              },
              onInput: function (t) {
                var n = (0, o.countLetters)(this.title),
                  e = n.english,
                  i = n.chinese;
                this.num = Math.ceil((2 * i + e) / 2);
              },
              submitBlog: function () {
                if (this.num > 25)
                  t.showToast({ icon: "none", title: "最多添加25个字" });
                else if (0 == this.num)
                  t.showToast({ icon: "none", title: "请输入课程表标题" });
                else {
                  var n = {};
                  (n.title = this.title),
                    (0, o.savePlanTitle)(n).then(function (n) {
                      200 == n.code
                        ? (t.showToast({ icon: "none", title: "保存成功" }),
                          setTimeout(function () {
                            t.navigateBack({ delta: 1 });
                          }, 1e3))
                        : t.showToast({ icon: "none", title: n.msg });
                    });
                }
              },
            },
          };
        n.default = i;
      }).call(this, e("df3c").default);
    },
    "8eb0": function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("3d78"),
        i = e.n(o);
      for (var u in o)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(u);
      n.default = i.a;
    },
    c6a8: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("cf8f"),
        i = e("8eb0");
      for (var u in i)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return i[t];
            });
          })(u);
      e("1c25");
      var a = e("828b"),
        c = Object(a.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "4897fa90",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = c.exports;
    },
    cf8f: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return i;
      }),
        e.d(n, "c", function () {
          return u;
        }),
        e.d(n, "a", function () {
          return o;
        });
      var o = {
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        i = function () {
          this.$createElement;
          this._self._c;
        },
        u = [];
    },
    fcff: function (t, n, e) {},
  },
  [["33e4", "common/runtime", "common/vendor"]],
]);
