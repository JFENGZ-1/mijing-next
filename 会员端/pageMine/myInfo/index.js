(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageMine/myInfo/index"],
  {
    1524: function (e, t, n) {},
    "37af": function (e, t, n) {
      (function (e) {
        var o = n("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var r = o(n("7ca3")),
          i = o(n("3387")),
          a = n("888d"),
          u = o(n("bd1e"));
        function s(e, t) {
          var n = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var o = Object.getOwnPropertySymbols(e);
            t &&
              (o = o.filter(function (t) {
                return Object.getOwnPropertyDescriptor(e, t).enumerable;
              })),
              n.push.apply(n, o);
          }
          return n;
        }
        function c(e) {
          for (var t = 1; t < arguments.length; t++) {
            var n = null != arguments[t] ? arguments[t] : {};
            t % 2
              ? s(Object(n), !0).forEach(function (t) {
                  (0, r.default)(e, t, n[t]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    e,
                    Object.getOwnPropertyDescriptors(n),
                  )
                : s(Object(n)).forEach(function (t) {
                    Object.defineProperty(
                      e,
                      t,
                      Object.getOwnPropertyDescriptor(n, t),
                    );
                  });
          }
          return e;
        }
        var l = {
          data: function () {
            return {
              form: {},
              actionSheetShow: !1,
              timeActionShow: !1,
              actionSheetList: [],
              infoList: [],
              selectedKey: "",
              defaultTime: "",
              defaultValue: [0],
              keys: [
                { text: "手机号", key: "userPhone", type: "input" },
                {
                  text: "性别",
                  key: "userSex",
                  type: "selector",
                  selectorList: [
                    { label: "男", value: 1 },
                    { label: "女", value: 2 },
                  ],
                },
                { text: "生日", key: "userBirthday", type: "selector" },
                { text: "身份证", key: "userIdent", type: "input" },
                { text: "姓名", key: "userNickname", type: "input" },
                {
                  text: "身高",
                  key: "userHeight",
                  type: "selector",
                  selectorList: [],
                },
                {
                  text: "体重",
                  key: "userWeight",
                  type: "selector",
                  selectorList: [],
                },
              ],
              timeParams: { year: !0, month: !0, day: !0 },
            };
          },
          computed: {
            commonData: function () {
              return this.$store.state.commonData;
            },
          },
          methods: {
            formItemClick: function (t) {
              var n = this,
                o = t.key,
                r = this.keys.find(function (e) {
                  return e.key === o;
                });
              if (((this.selectedKey = o), "selector" == r.type))
                if ("userBirthday" == o)
                  (this.timeActionShow = !0),
                    this.form[o]
                      ? (this.defaultTime = this.form[o])
                      : (this.defaultTime = "2000-01-01");
                else {
                  (this.actionSheetShow = !0),
                    (this.actionSheetList = r.selectorList);
                  var a = r.selectorList.findIndex(function (e) {
                    return e.label == n.form[o];
                  });
                  this.defaultValue = -1 !== a ? [a] : [30];
                }
              else {
                e.navigateTo({
                  url: "/pageMine/modifidInfo/index?selectedKey=".concat(o),
                });
                var u = this.form,
                  s = this.keys,
                  c = this.selectedKey,
                  l = this.infoList;
                this.$store.dispatch(
                  "getMineInfo",
                  i.default.cloneDeep({
                    form: u,
                    keys: s,
                    selectedKey: c,
                    infoList: l,
                  }),
                );
              }
            },
            timeActionCallback: function (e) {
              var t = e.year,
                n = e.month,
                o = e.day,
                r = "".concat(t, "-").concat(n, "-").concat(o);
              (this.form[this.selectedKey] = r), this.saveInfo();
            },
            actionSheetCallback: function (e) {
              (this.form[this.selectedKey] = e[0].label), this.saveInfo();
            },
            selectedSex: function () {
              this.actionSheetShow = !0;
            },
            chooseAvatar: function () {
              this.$u.route({
                url: "pages/tailor/u-avatar-cropper/u-avatar-cropper",
                params: { destWidth: 200, rectWidth: 350, fileType: "jpg" },
              });
            },
            getUserInfo: function () {
              var t = this;
              (0, a.getMyUserInfo)().then(function (n) {
                if (200 == n.code) {
                  var o = n.userField.map(function (e) {
                      for (var n = 0; n < t.keys.length; n++)
                        t.keys[n].text == e.text &&
                          (e = c(c({}, e), {}, { key: t.keys[n].key }));
                      return e;
                    }),
                    r = n.user,
                    i = r.userFaceurl,
                    a = r.userSex,
                    u = r.userNickname,
                    s = r.userIdent,
                    l = r.userPhone,
                    f = r.userBirthday,
                    d = r.userHeight,
                    h = r.userWeight,
                    m = (f || "").substring(0, 10);
                  (t.infoList = o),
                    (t.form = {
                      userFaceurl: i,
                      userSex: a,
                      userNickname: u,
                      userIdent: s,
                      userPhone: l,
                      userBirthday: m,
                      userHeight: d,
                      userWeight: h,
                    });
                  for (
                    var p = t.keys.filter(function (e) {
                        return "selector" == e.type && "userBirthday" != e.key;
                      }),
                      y = function (e) {
                        var n = t.form[p[e].key];
                        if (null != n) {
                          var o = p[e].selectorList.find(function (e) {
                            return e.value == n;
                          });
                          o && (t.form[p[e].key] = o.label);
                        }
                      },
                      v = 0;
                    v < p.length;
                    v++
                  )
                    y(v);
                } else e.showToast({ title: n.msg, icon: "none", mask: !0 });
              });
            },
            saveInfo: function () {
              for (
                var t = i.default.cloneDeep(this.form),
                  n = this.keys.filter(function (e) {
                    return "selector" == e.type && "userSex" == e.key;
                  }),
                  o = function (e) {
                    var o = t[n[e].key];
                    null != o &&
                      (t[n[e].key] = n[e].selectorList.find(function (e) {
                        return e.label == o;
                      }).value);
                  },
                  r = 0;
                r < n.length;
                r++
              )
                o(r);
              (0, a.UpdateUserInfo)(t).then(function (t) {
                e.showToast({
                  title: 200 === t.code ? "修改成功" : t.msg,
                  icon: "none",
                });
              });
            },
          },
          created: function () {
            var t = this;
            e.$on("uAvatarCropper", function (n) {
              e.showLoading({ title: "上传中", mask: !0 });
              var o = t;
              e.uploadFile({
                url: "".concat(u.default.baseUrl, "/common/uploadfile"),
                filePath: n,
                name: "file",
                complete: function (t) {
                  e.hideLoading();
                  var n = JSON.parse(t.data);
                  200 == n.code
                    ? (e.showToast({ title: "上传成功", icon: "none" }),
                      (o.form.userFaceurl = n.dbUrl),
                      o.saveInfo(),
                      e.navigateBack({ delta: 1 }))
                    : e.showToast({ title: t.msg, icon: "none" });
                },
              });
            }),
              e.$on("ok", function () {
                e.navigateBack({ delta: 1 }), t.getUserInfo();
              });
          },
          onLoad: function (t) {
            t.title && e.setNavigationBarTitle({ title: t.title }),
              this.keys.forEach(function (e) {
                if ("userHeight" == e.key)
                  for (var t = 100; t < 200; t++)
                    e.selectorList.push({
                      label: "".concat(t, "cm"),
                      value: t,
                    });
                if ("userWeight" == e.key)
                  for (var n = 20; n < 120; n++)
                    e.selectorList.push({
                      label: "".concat(n, "kg"),
                      value: n,
                    });
              }),
              this.getUserInfo();
          },
          onUnload: function () {
            e.$off("uAvatarCropper"), e.$off("ok");
          },
        };
        t.default = l;
      }).call(this, n("df3c").default);
    },
    "4bb5": function (e, t, n) {
      n.r(t);
      var o = n("718c"),
        r = n("9d5c");
      for (var i in r)
        ["default"].indexOf(i) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return r[e];
            });
          })(i);
      n("f2f8");
      var a = n("828b"),
        u = Object(a.a)(
          r.default,
          o.b,
          o.c,
          !1,
          null,
          "dea11e0c",
          null,
          !1,
          o.a,
          void 0,
        );
      t.default = u.exports;
    },
    "718c": function (e, t, n) {
      n.d(t, "b", function () {
        return r;
      }),
        n.d(t, "c", function () {
          return i;
        }),
        n.d(t, "a", function () {
          return o;
        });
      var o = {
          uForm: function () {
            return n
              .e("node-modules/uview-ui/components/u-form/u-form")
              .then(n.bind(null, "64a7"));
          },
          uFormItem: function () {
            return Promise.all([
              n.e("common/vendor"),
              n.e("node-modules/uview-ui/components/u-form-item/u-form-item"),
            ]).then(n.bind(null, "e2b1"));
          },
          uInput: function () {
            return Promise.all([
              n.e("common/vendor"),
              n.e("node-modules/uview-ui/components/u-input/u-input"),
            ]).then(n.bind(null, "13aa"));
          },
          uSelect: function () {
            return n
              .e("node-modules/uview-ui/components/u-select/u-select")
              .then(n.bind(null, "5eee"));
          },
          uPicker: function () {
            return Promise.all([
              n.e("common/vendor"),
              n.e("node-modules/uview-ui/components/u-picker/u-picker"),
            ]).then(n.bind(null, "2fc3"));
          },
        },
        r = function () {
          this.$createElement;
          var e = (this._self._c, this.imgsrc("/static/imgs/camera.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: e } });
        },
        i = [];
    },
    "8f4b": function (e, t, n) {
      (function (e, t) {
        var o = n("47a9");
        n("9785"), o(n("3240"));
        var r = o(n("4bb5"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = n), t(r.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
    "9d5c": function (e, t, n) {
      n.r(t);
      var o = n("37af"),
        r = n.n(o);
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return o[e];
            });
          })(i);
      t.default = r.a;
    },
    f2f8: function (e, t, n) {
      var o = n("1524");
      n.n(o).a;
    },
  },
  [["8f4b", "common/runtime", "common/vendor"]],
]);
