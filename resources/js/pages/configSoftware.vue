<template>
    <g-layout>
        <v-card class="card-100 ml-14">
            <v-card-title>
                <div>Программное обеспечение</div>
                <v-spacer></v-spacer>
                <v-dialog v-model="dialog" max-width="1000px">
                    <template v-slot:activator="{ on }">
                        <v-btn color="primary" dark class="mb-2" v-on="on" @click="addNew">Добавить ПО</v-btn>
                    </template>
                    <v-card>
                        <v-card-title>
                            <span class="headline">{{ formTitle }}</span>
                            <v-spacer />
                            <v-btn color="red" class="white--text" @click="deleteItem">Удалить</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <v-form ref="addSoftware" @submit.prevent="submit">
                                <v-form-base :model="formModel" :schema="formSchema" @input="formChange"/>
                                <v-card-actions>
                                    <v-btn plain @click="close">Отмена</v-btn>
                                    <v-spacer />
                                    <v-btn type="submit" color="primary" :disabled="submitDisabled">Сохранить</v-btn>
                                </v-card-actions>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </v-dialog>
            </v-card-title>
            <v-card-text>
                <dictionary-loader
                    :items="['server','service_type_group']"
                >
                    <v-skeleton-loader v-if="dictionaryLoading" type="table"/>
                    <v-data-table
                        v-else
                        :headers="headersWithControls"
                        :items="software"
                        :server-items-length="count"
                        :options.sync="tablePageSort"
                        class=""
                        :loading="waitingIndicator"
                        :footer-props="{'items-per-page-options': [15,25,50,100,250,500]}"
                    >
                        <template v-for="header in headersWithControls" v-slot:[`header.${header.value}`]>
                            <div style="min-height: 80px;">
                                <div >{{header.text}}</div>
                                <div v-if="header.dictionary" @click.stop.prevent>
                                    <v-autocomplete
                                        :items="getDictionary(header.dictionary)"
                                        v-model="filter[header.value]"
                                        clearable multiple
                                    />
                                </div>
                            </div>
                        </template>
                        <template v-for="header in headersWithControls" v-slot:[`item.${header.value}`]="{item}">
                            <template v-if="header.value === 'controls_edit'">
                                <td class="justify-center">
                                    <v-icon
                                        small
                                        class="mr-2"
                                        @click="editItem(item.id)"
                                    >
                                        mdi-pencil
                                    </v-icon>
                                </td>
                            </template>
                            <template v-else>
                                {{itemValue(item, header)}}
                            </template>
                        </template>
        
                        <template v-slot:no-data>
                            <v-btn color="primary" @click="initialize">Обновить</v-btn>
                        </template>
                    </v-data-table>
                </dictionary-loader>
            </v-card-text>
        </v-card>
    </g-layout>
</template>

<script>
import {
    mapState
} from "vuex";
import {
    Inertia
} from "@inertiajs/inertia";
import axios
    from "axios";
import {
    SweetAlert
} from "../helpers/SweetAlert";

const REQUIRED = (v) => !!v || 'обязательно для заполнения'

export default {
    name: "Home",
    components: {},
    props: [
        'data', 'servers', 'headers'
    ],
    data: () => ({
        load: false,
        isNew: false,
        dialog: false,
        editedIndex: -1,
        requestStarted: false,
        item: [],
        editedItem: {
            server_id: [],
            title: '',
            type_id: 3,
            version: '',
        },
        defaultItem: {
            server_id: [],
            title: '',
            type_id: 3,
            version: '',
        },
        filter: {
            server_id: [],
            type_id: []
        },
        tablePageSort: {"page":1,"itemsPerPage":100,"sortBy":["title"],"sortDesc":[false],"sortAsc":[true],"groupBy":[],"groupDesc":[],"mustSort":false,"multiSort":false},
        software: [],
    }),

    computed: {
        ...mapState('dictionary', ['dictionary', 'dictionaryLoading']),
        count(){
            return this.data?.count ?? -1
        },
        submitDisabled(){
            return this.requestStarted
        },
        formModel(){
            return {
                ...this.isNew ? this.defaultItem : this.editedItem
            }
        },
        formTitle () {
            return this.editedIndex === -1 ? 'Добавление ПО' : 'Редактирование ПО'
        },
        headersWithControls(){
            this.headers.splice(1, 0, {value: 'server_id', text: 'Сервер', dictionary: 'server'});
            return [{value: 'controls_edit', sortable: false},...this.headers]
        },
        formSchema() {
            return this.dictionaryLoading?{}:{
                title: {
                    type:'text',
                    label:'Название',
                    col: 12,
                    rules: [REQUIRED],
                    validateOnBlur: true
                },
                server_id: {
                    type:'autocomplete', label:'Сервер',
                    items: this.servers,
                    multiple: true,
                    chips: true,
                    'deletable-chips': true,
                    'hide-selected': true,
                    'small-chips': true,
                    col: 12
                },
                type_id: {
                    type:'autocomplete', label:'Тип',
                    items: this.dictionary.service_type_group,
                    col: 12
                },
                version: {type:'text', label:'Версия', col: 12},
            }
        },
        waitingIndicator() {
            return (this.load === true) ? 'loading' : false;
        }
    },

    mounted() {
        this.initialize()

        if(this.isNew){
            this.editedItem = {
                ...this.defaultItem
            }
        }
    },

    watch: {
        tablePageSort(){
            this.loadData()
        },
        data(){
            this.initialize()
        },
        filter: {
            deep: true,
            handler() {
                this.loadData()
            }
        },
        load(enable) {
            return (enable) ? 'loading' : '';
        }
    },

    methods: {
        initialize () {
            let soft = this.data?.items ?? [];
            if (this.data?.items) {
                this.software = soft.reduce((m, o) => {
                    let found = m.find(p => p.id === o.id);
                    if (found) {
                        found.server_id += ',' + o.server_id;
                        found.server_id = found.server_id.split(',');
                        found.server_id = found.server_id.map(el => parseInt(el));
                    } else {
                        if (o.server_id !== null) {
                            o.server_id = [parseInt(o.server_id)];
                        }
                        if (o.type_id !== null) {
                            o.type_id = parseInt(o.type_id);
                        }
                        m.push(o);
                    }
                    return m;
                }, []);
            } else {
                this.software = [];
            }
            this.load = false;
        },

        getDictionary(title){
            if (title === 'server') {
                return this.servers;
            }
            return  this.dictionary?.[title] ?? []
        },

        itemValue(item, header){
            let val = item[header.value]
            if(header.dictionary && val !== null){
                if (header.dictionary === 'server') {
                    if (val.length >= 1) {
                        let obj = this;
                        let res = [];
                        val.forEach(function (value){
                            res.push(obj.getDictionary(header.dictionary)?.find(v => v.value === parseInt(value))?.text);
                        });
                        return res.join(', ');
                    } else {
                        return this.getDictionary(header.dictionary)?.find(v => v.value === parseInt(val))?.text
                    }
                } else {
                    return this.getDictionary(header.dictionary)?.find(v => v.value === parseInt(val))?.text
                }
            }
            return  val
        },

        editItem (id) {
            this.isNew = false;
            const indexes = this.software.map(el => el.id);
            /*
            0: 150
            1: 149
            2: 18
            3: 4
            * */
            this.editedIndex = indexes.indexOf(id);
            this.editedItem = Object.assign({}, this.software[this.editedIndex]);
            this.dialog = true
        },

        addNew () {
            this.isNew = true;
            this.editedItem = Object.assign({}, this.defaultItem)
        },

        close () {
            this.isNew = true;
            this.dialog = false;
        },

        reload(){
            this.load = true;
            this.loadData().then(() => {
                this.initialize();
            });
        },

        async loadData() {
            this.load = true;
            Inertia.reload({
                only: ['data'],
                method: 'post',
                data: {
                    tablePageSort: this.tablePageSort,
                    filter: this.filter,
                }
            })
        },

        formChange(e){
            this.editedItem[e.key] = e.value
            if (e.key === 'server_id') {
                this.defaultItem[e.key] = e.value;
            }
        },

        async submit(){
            this.requestStarted = true;
            this.load = true;
            await axios.post('/softwareSave', {...this.editedItem}).then((response) => {
                this.requestStarted = false;
                if (response.data.success !== undefined) {
                    this.isNew = true;
                    this.close();
                    this.reload();
                    SweetAlert.toast(response.data.success);
                    this.$refs.addSoftware.inputs[0].reset(); //очищаем Title
                    this.$refs.addSoftware.$el[3].value = "ОПО";
                    this.$refs.addSoftware.inputs[3].reset(); //и Версию
                }
                if (response.data.error !== undefined) {
                    this.load = false;
                    SweetAlert.toast(response.data.error, 'error');
                }
            });
        },
    
        async deleteItem(){
            let item = this.software[this.editedIndex];
            let confirm = await SweetAlert.confirm('Вы уверены?')
            if(confirm){
                this.load = true;
                let res = await axios.delete('/software_resource/'+item.id);
                if(res.data.success){
                    this.show = false
                    SweetAlert.toast(res.data.success);
                } else {
                    SweetAlert.toast(res.data.error, 'error');
                    console.error(res?.data?.trace);
                }
                this.reload();
                this.close();
            }
        }
    }
}
</script>

<style>
.v-data-table-header th[role="columnheader"]:not(.sortable) {
    position: relative;
}
</style>