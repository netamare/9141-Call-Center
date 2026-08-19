import {Schema,model,models} from "mongoose";
const SettingSchema=new Schema({key:{type:String,unique:true,required:true},value:Schema.Types.Mixed,updatedBy:{type:Schema.Types.ObjectId,ref:"User"}},{timestamps:true});
export default models.Setting||model("Setting",SettingSchema);
